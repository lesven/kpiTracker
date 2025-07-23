<?php

namespace App\Controller;

use App\Entity\KPI;
use App\Entity\MailSettings;
use App\Entity\User;
use App\Form\KPIAdminType;
use App\Form\MailSettingsType;
use App\Form\UserType;
use App\Repository\KPIRepository;
use App\Repository\KPIValueRepository;
use App\Repository\MailSettingsRepository;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin-Controller für Benutzerverwaltung
 * User Stories 2, 4: Administrator kann Benutzer und KPIs anlegen.
 */
#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private KPIRepository $kpiRepository,
        private UserService $userService,
        private UserPasswordHasherInterface $passwordHasher,
        private KPIValueRepository $kpiValueRepository,
        private MailSettingsRepository $mailSettingsRepository,
    ) {
    }

    /**
     * Admin-Dashboard mit System-Übersicht.
     */
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = [
            'total_users' => $this->userRepository->countUsers(),
            'total_admins' => $this->userRepository->countAdmins(),
            'total_kpis' => $this->kpiRepository->countAll(),
            'recent_users' => $this->userRepository->findCreatedBetween(
                new \DateTimeImmutable('-30 days'),
                new \DateTimeImmutable()
            ),
            'kpis_by_user' => $this->kpiRepository->countKpisByUser(),
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Benutzerverwaltung - Liste aller Benutzer.
     */
    #[Route('/users', name: 'app_admin_users')]
    public function users(): Response
    {
        $users = $this->userRepository->findAll();

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Neuen Benutzer anlegen
     * User Story 2: Administrator kann Benutzer anlegen.
     */
    #[Route('/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Temporäres Passwort hashen
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Benutzer "'.$user->getEmail().'" wurde erfolgreich erstellt.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Benutzer bearbeiten.
     */
    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Neues Passwort setzen falls eingegeben
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Benutzer wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Benutzer löschen (DSGVO-konform).
     */
    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            // Prüfen ob der Admin sich nicht selbst löscht
            if ($user === $this->getUser()) {
                $this->addFlash('error', 'Sie können sich nicht selbst löschen.');

                return $this->redirectToRoute('app_admin_users');
            }

            $email = $user->getEmail();
            $this->userService->deleteUserWithData($user);

            $this->addFlash('success', 'Benutzer "'.$email.'" und alle zugehörigen Daten wurden DSGVO-konform gelöscht.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    /**
     * KPI-Verwaltung - Alle KPIs aller Benutzer.
     */
    #[Route('/kpis', name: 'app_admin_kpis')]
    public function kpis(): Response
    {
        $kpis = $this->kpiRepository->findAllWithUser();
        $lastValues = [];

        foreach ($kpis as $kpi) {
            $lastValues[$kpi->getId()] = $this->kpiValueRepository->findLatestValueForKpi($kpi);
        }

        return $this->render('admin/kpis/index.html.twig', [
            'kpis' => $kpis,
            'last_values' => $lastValues,
        ]);
    }

    /**
     * Exportiert alle KPI-Werte als Excel-Datei.
     */
    #[Route('/kpis/export', name: 'app_admin_kpi_export', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exportKpis(): Response
    {
        $values = $this->kpiValueRepository->findForAdminExport();

        $response = new StreamedResponse(function () use ($values) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header
            $sheet->fromArray([
                'username', 'kpiName', 'kpiWert', 'kpiDatum', 'kpiEinheit',
            ], null, 'A1');

            $row = 2;
            foreach ($values as $value) {
                $kpi = $value->getKpi();
                $user = $kpi ? $kpi->getUser() : null;

                $sheet->setCellValue('A'.$row, $user ? $user->getEmail() : 'N/A');
                $sheet->setCellValue('B'.$row, $kpi ? $kpi->getName() : 'N/A');
                $sheet->setCellValue('C'.$row, $value->getValue());
                $sheet->setCellValue('D'.$row, $value->getPeriod());
                $sheet->setCellValue('E'.$row, $kpi ? $kpi->getUnit() : 'N/A');
                ++$row;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="kpi_export.xlsx"');

        return $response;
    }

    /**
     * KPI für Benutzer anlegen
     * User Story 4: Administrator kann KPIs für Benutzer anlegen.
     */
    #[Route('/kpis/new', name: 'app_admin_kpi_new', methods: ['GET', 'POST'])]
    public function newKpi(Request $request): Response
    {
        $kpi = new KPI();
        $form = $this->createForm(KPIAdminType::class, $kpi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($kpi);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI "'.$kpi->getName().'" wurde für '.$kpi->getUser()->getEmail().' erstellt.');

            return $this->redirectToRoute('app_admin_kpis');
        }

        return $this->render('admin/kpis/new.html.twig', [
            'kpi' => $kpi,
            'form' => $form,
        ]);
    }

    /**
     * Admin-KPI bearbeiten.
     */
    #[Route('/kpis/{id}/edit', name: 'app_admin_kpi_edit', methods: ['GET', 'POST'])]
    public function editKpi(Request $request, KPI $kpi): Response
    {
        $form = $this->createForm(KPIAdminType::class, $kpi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_admin_kpis');
        }

        return $this->render('admin/kpis/edit.html.twig', [
            'kpi' => $kpi,
            'form' => $form,
        ]);
    }

    /**
     * Admin-KPI löschen.
     */
    #[Route('/kpis/{id}/delete', name: 'app_admin_kpi_delete', methods: ['POST'])]
    public function deleteKpi(Request $request, KPI $kpi): Response
    {
        if ($this->isCsrfTokenValid('delete'.$kpi->getId(), $request->request->get('_token'))) {
            $kpiName = $kpi->getName();
            $userEmail = $kpi->getUser()->getEmail();

            $this->entityManager->remove($kpi);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI "'.$kpiName.'" von '.$userEmail.' wurde gelöscht.');
        }

        return $this->redirectToRoute('app_admin_kpis');
    }

    #[Route('/settings/mail', name: 'app_admin_mail_settings', methods: ['GET', 'POST'])]
    public function mailSettings(Request $request): Response
    {
        $settings = $this->mailSettingsRepository->findOneBy([]) ?? new MailSettings();

        $form = $this->createForm(MailSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($settings);
            $this->entityManager->flush();

            $this->addFlash('success', 'E-Mail-Einstellungen wurden gespeichert.');

            return $this->redirectToRoute('app_admin_mail_settings');
        }

        return $this->render('admin/settings/mail.html.twig', [
            'form' => $form,
        ]);
    }
}
