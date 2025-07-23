<?php

namespace App\Controller;

use App\Entity\KPI;
use App\Entity\KPIValue;
use App\Entity\User;
use App\Form\KPIType;
use App\Form\KPIValueType;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Service\FileUploadService;
use App\Service\KPIService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * KPI-Controller für Verwaltung von KPIs und Werten
 * User Stories 3, 5: KPI anlegen und Werte erfassen.
 */
#[Route('/kpi')]
#[IsGranted('ROLE_USER')]
class KPIController extends AbstractController
{
    // Sortierungs-Konstanten für KPI-Listen
    public const SORT_NAME = 'name';
    public const SORT_DUE = 'due';
    public const SORT_STATUS = 'status';
    public const SORT_CREATED = 'created';
    
    // Cookie für Sortierung
    private const SORT_COOKIE = 'kpi_sort';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private KPIRepository $kpiRepository,
        private KPIValueRepository $kpiValueRepository,
        private KPIService $kpiService,
        private FileUploadService $fileUploadService,
    ) {
    }

    /**
     * Liste aller KPIs des Benutzers.
     * Unterstützt optionale Sortierung nach verschiedenen Kriterien.
     */
    #[Route('/', name: 'app_kpi_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $sortParam = $request->query->get('sort');
        $allowedSortValues = [self::SORT_NAME, self::SORT_DUE];
        $sort = in_array($sortParam, $allowedSortValues, true)
            ? $sortParam
            : $request->cookies->get(self::SORT_COOKIE, self::SORT_NAME);

        /** @var User $user */
        $user = $this->getUser();

        // KPIs laden und sortieren
        $kpis = $this->kpiRepository->findByUser($user, $sort);

        // Spezielle Sortierung für Fälligkeitsdatum
        if (self::SORT_DUE === $sort) {
            usort($kpis, static fn (KPI $a, KPI $b) =>
                ($a->getNextDueDate() ?? PHP_INT_MAX) <=> ($b->getNextDueDate() ?? PHP_INT_MAX)
            );
        }

        $response = $this->render('kpi/index.html.twig', [
            'kpis' => $kpis,
            'current_sort' => $sort,
        ]);

        // Cookie setzen wenn Sortierung explizit gewählt wurde
        if ($sortParam) {
            $response->headers->setCookie(new Cookie(self::SORT_COOKIE, $sort, strtotime('+1 year')));
        }

        return $response;
    }

    /**
     * Neue KPI erstellen
     * User Story 3: Benutzer kann KPI anlegen.
     */
    #[Route('/new', name: 'app_kpi_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $kpi = new KPI();
        $form = $this->createForm(KPIType::class, $kpi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            $kpi->setUser($user);

            $this->entityManager->persist($kpi);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI "'.$kpi->getName().'" wurde erfolgreich erstellt.');

            return $this->redirectToRoute('app_kpi_index');
        }

        return $this->render('kpi/new.html.twig', [
            'kpi' => $kpi,
            'form' => $form,
        ]);
    }

    /**
     * KPI-Details anzeigen mit Historie aller Werte.
     */
    #[Route('/{id}', name: 'app_kpi_show', methods: ['GET'])]
    public function show(KPI $kpi): Response
    {
        // Prüfen ob KPI dem aktuellen Benutzer gehört
        $this->denyAccessUnlessGranted('view', $kpi);

        $values = $this->kpiValueRepository->findByKPI($kpi);
        $status = $this->kpiService->getKpiStatus($kpi);

        return $this->render('kpi/show.html.twig', [
            'kpi' => $kpi,
            'values' => $values,
            'status' => $status,
        ]);
    }

    /**
     * KPI bearbeiten.
     */
    #[Route('/{id}/edit', name: 'app_kpi_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, KPI $kpi): Response
    {
        $this->denyAccessUnlessGranted('edit', $kpi);

        $form = $this->createForm(KPIType::class, $kpi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_kpi_show', ['id' => $kpi->getId()]);
        }

        return $this->render('kpi/edit.html.twig', [
            'kpi' => $kpi,
            'form' => $form,
        ]);
    }

    /**
     * KPI löschen.
     */
    #[Route('/{id}/delete', name: 'app_kpi_delete', methods: ['POST'])]
    public function delete(Request $request, KPI $kpi): Response
    {
        $this->denyAccessUnlessGranted('delete', $kpi);

        if ($this->isCsrfTokenValid('delete'.$kpi->getId(), $request->request->get('_token'))) {
            $kpiName = $kpi->getName();
            $this->entityManager->remove($kpi);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI "'.$kpiName.'" und alle zugehörigen Werte wurden gelöscht.');
        }

        return $this->redirectToRoute('app_kpi_index');
    }

    /**
     * Neuen KPI-Wert erfassen
     * User Story 5: Benutzer kann KPI-Werte erfassen.
     */
    #[Route('/{id}/add-value', name: 'app_kpi_add_value', methods: ['GET', 'POST'])]
    public function addValue(Request $request, KPI $kpi): Response
    {
        $this->denyAccessUnlessGranted('add_value', $kpi);

        $kpiValue = new KPIValue();
        $kpiValue->setKpi($kpi);

        // Aktuellen Zeitraum als Standardwert vorschlagen
        $currentPeriod = $kpi->getCurrentPeriod();
        $kpiValue->setPeriod($currentPeriod);

        $form = $this->createForm(KPIValueType::class, $kpiValue);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submittedPeriod = $kpiValue->getPeriod();

            // Prüfen ob bereits ein Wert für diesen Zeitraum existiert
            $existingValue = $this->kpiValueRepository->findByKpiAndPeriod($kpi, $submittedPeriod);

            if ($existingValue) {
                $this->addFlash('warning', 'Für den Zeitraum "'.$submittedPeriod.'" existiert bereits ein Wert. Bitte bearbeiten Sie den bestehenden Wert oder wählen Sie einen anderen Zeitraum.');

                return $this->redirectToRoute('app_kpi_show', ['id' => $kpi->getId()]);
            }

            $this->entityManager->persist($kpiValue);
            $this->entityManager->flush();

            // Datei-Uploads verarbeiten
            $uploadedFiles = $form->get('uploadedFiles')->getData();
            if ($uploadedFiles) {
                $uploadStats = $this->fileUploadService->handleFileUploads($uploadedFiles, $kpiValue);

                if ($uploadStats['uploaded'] > 0) {
                    $this->addFlash('success', "{$uploadStats['uploaded']} Datei(en) erfolgreich hochgeladen.");
                }

                if ($uploadStats['failed'] > 0) {
                    foreach ($uploadStats['errors'] as $error) {
                        $this->addFlash('warning', $error);
                    }
                }

                $this->entityManager->flush();
            }

            $this->addFlash('success', 'KPI-Wert wurde erfolgreich gespeichert.');

            return $this->redirectToRoute('app_kpi_show', ['id' => $kpi->getId()]);
        }

        return $this->render('kpi/add_value.html.twig', [
            'kpi' => $kpi,
            'kpi_value' => $kpiValue,
            'form' => $form,
            'current_period' => $currentPeriod,
        ]);
    }

    /**
     * KPI-Wert bearbeiten
     * User Story 8: KPI-Wertliste und nachträgliche Bearbeitung.
     */
    #[Route('/value/{id}/edit', name: 'app_kpi_value_edit', methods: ['GET', 'POST'])]
    public function editValue(Request $request, KPIValue $kpiValue): Response
    {
        $this->denyAccessUnlessGranted('edit', $kpiValue->getKpi());

        $form = $this->createForm(KPIValueType::class, $kpiValue);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $kpiValue->markAsUpdated();
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI-Wert wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_kpi_show', ['id' => $kpiValue->getKpi()->getId()]);
        }

        return $this->render('kpi/edit_value.html.twig', [
            'kpi_value' => $kpiValue,
            'kpi' => $kpiValue->getKpi(),
            'form' => $form,
        ]);
    }

    /**
     * KPI-Wert löschen
     * User Story 8: KPI-Wertliste und nachträgliche Bearbeitung.
     */
    #[Route('/value/{id}/delete', name: 'app_kpi_value_delete', methods: ['POST'])]
    public function deleteValue(Request $request, KPIValue $kpiValue): Response
    {
        $kpi = $kpiValue->getKpi();
        $this->denyAccessUnlessGranted('delete', $kpi);

        if ($this->isCsrfTokenValid('delete'.$kpiValue->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($kpiValue);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI-Wert wurde gelöscht.');
        }

        return $this->redirectToRoute('app_kpi_show', ['id' => $kpi->getId()]);
    }
}
