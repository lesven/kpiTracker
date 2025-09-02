<?php

namespace App\Controller;

use App\Domain\ValueObject\EmailAddress;
use App\Entity\KPI;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Form\KPIAdminType;
use App\Form\MailSettingsType;
use App\Form\UserType;
use App\Repository\KPIValueRepository;
use App\Repository\UserRepository;
use App\Service\AdminService;
use App\Service\ExcelExportService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        private UserService $userService,
        private KPIValueRepository $kpiValueRepository,
        private ExcelExportService $excelExportService,
        private AdminService $adminService,
        private UserFactory $userFactory,
    ) {
    }

    /**
     * Zeigt das Admin-Dashboard mit Systemstatistiken.
     *
     * @return Response Die gerenderte Dashboard-Seite
     */
    #[Route('/', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        $stats = $this->adminService->getDashboardStats();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Zeigt die Benutzerverwaltung mit einer Liste aller Benutzer.
     *
     * @return Response Die gerenderte Benutzerliste
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
     * Legt einen neuen Benutzer an (User Story 2).
     *
     * @param Request $request HTTP-Request mit Formulardaten
     *
     * @return Response Die Seite zum Anlegen eines Benutzers oder Redirect nach Erfolg
     */
    #[Route('/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(Request $request): Response
    {
        $user = $this->userFactory->createRegularUser('', '', '');
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $this->adminService->createUser($user, $plainPassword);

            $this->addFlash('success', 'Benutzer "'.$user->getEmail()->getValue().'" wurde erfolgreich erstellt.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Bearbeitet einen bestehenden Benutzer.
     *
     * @param Request $request HTTP-Request mit Formulardaten
     * @param User    $user    Zu bearbeitender Benutzer
     *
     * @return Response Die Seite zum Bearbeiten oder Redirect nach Erfolg
     */
    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $this->adminService->updateUser($user, $plainPassword);

            $this->addFlash('success', 'Benutzer wurde erfolgreich aktualisiert.');

            return $this->redirectToRoute('app_admin_users');
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * Löscht einen Benutzer DSGVO-konform inkl. aller zugehörigen Daten.
     *
     * @param Request $request HTTP-Request mit CSRF-Token
     * @param User    $user    Zu löschender Benutzer
     *
     * @return Response Redirect zur Benutzerliste
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

            $email = $user->getEmail()->getValue();
            $this->userService->deleteUserWithData($user);

            $this->addFlash('success', 'Benutzer "'.$email.'" und alle zugehörigen Daten wurden DSGVO-konform gelöscht.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    /**
     * Zeigt alle KPIs aller Benutzer für die Admin-Verwaltung.
     *
     * @return Response Die gerenderte KPI-Liste
     */
    #[Route('/kpis', name: 'app_admin_kpis')]
    public function kpis(): Response
    {
        [$kpis, $lastValues] = $this->adminService->getKpisWithLastValues();

        return $this->render('admin/kpis/index.html.twig', [
            'kpis' => $kpis,
            'last_values' => $lastValues,
        ]);
    }

    /**
     * Exportiert alle KPI-Werte als Excel-Datei (User Story 3).
     *
     * @return Response Die Excel-Export-Datei als Download
     */
    #[Route('/kpis/export', name: 'app_admin_kpi_export', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function exportKpis(): Response
    {
        $kpiValues = $this->kpiValueRepository->findForAdminExport();

        return $this->excelExportService->createKpiExportResponse($kpiValues);
    }

    /**
     * Legt eine neue KPI für einen Benutzer an (User Story 4).
     *
     * @param Request $request HTTP-Request mit Formulardaten
     *
     * @return Response Die Seite zum Anlegen einer KPI oder Redirect nach Erfolg
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

            $this->addFlash('success', 'KPI "'.$kpi->getName().'" wurde für '.$kpi->getUser()->getEmail()->getValue().' erstellt.');

            return $this->redirectToRoute('app_admin_kpis');
        }

        return $this->render('admin/kpis/new.html.twig', [
            'kpi' => $kpi,
            'form' => $form,
        ]);
    }

    /**
     * Bearbeitet eine bestehende KPI als Admin.
     *
     * @param Request $request HTTP-Request mit Formulardaten
     * @param KPI     $kpi     Zu bearbeitende KPI
     *
     * @return Response Die Seite zum Bearbeiten oder Redirect nach Erfolg
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
     * Löscht eine KPI als Admin.
     *
     * @param Request $request HTTP-Request mit CSRF-Token
     * @param KPI     $kpi     Zu löschende KPI
     *
     * @return Response Redirect zur KPI-Liste
     */
    #[Route('/kpis/{id}/delete', name: 'app_admin_kpi_delete', methods: ['POST'])]
    public function deleteKpi(Request $request, KPI $kpi): Response
    {
        if ($this->isCsrfTokenValid('delete'.$kpi->getId(), $request->request->get('_token'))) {
            $kpiName = $kpi->getName();
            $userEmail = $kpi->getUser()->getEmail()->getValue();

            $this->entityManager->remove($kpi);
            $this->entityManager->flush();

            $this->addFlash('success', 'KPI "'.$kpiName.'" von '.$userEmail.' wurde gelöscht.');
        }

        return $this->redirectToRoute('app_admin_kpis');
    }

    /**
     * Zeigt und speichert die E-Mail-Einstellungen für Erinnerungen.
     *
     * @param Request $request HTTP-Request mit Formulardaten
     *
     * @return Response Die Seite mit dem Einstellungsformular oder Redirect nach Erfolg
     */
    #[Route('/settings/mail', name: 'app_admin_mail_settings', methods: ['GET', 'POST'])]
    public function mailSettings(Request $request): Response
    {
        $settings = $this->adminService->getMailSettings();

        $form = $this->createForm(MailSettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->adminService->saveMailSettings($settings);

            $this->addFlash('success', 'E-Mail-Einstellungen wurden gespeichert.');

            return $this->redirectToRoute('app_admin_mail_settings');
        }

        return $this->render('admin/settings/mail.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Sendet Test-Erinnerungen per E-Mail (nur für Admins).
     *
     * @param Request $request HTTP-Request mit Test-E-Mail-Adresse
     *
     * @return Response Die Testseite mit Ergebnis
     */
    #[Route('/test-reminders', name: 'app_admin_test_reminders', methods: ['GET', 'POST'])]
    public function testReminders(Request $request): Response
    {
        $testEmail = '';
        $result = null;

        if ($request->isMethod('POST')) {
            $testEmail = $request->request->get('test_email', '');

            try {
                // Verwende EmailAddress Value Object für Validierung
                $emailAddress = new EmailAddress($testEmail);

                $success = $this->adminService->sendTestReminder($emailAddress->getValue());

                if ($success) {
                    $this->addFlash('success', "Test-E-Mail wurde erfolgreich an {$emailAddress->getValue()} gesendet. Überprüfen Sie MailHog unter http://localhost:8025");
                    $result = ['success' => true, 'email' => $emailAddress->getValue()];
                } else {
                    $this->addFlash('error', 'Fehler beim Senden der Test-E-Mail. Überprüfen Sie die Logs.');
                    $result = ['success' => false, 'email' => $emailAddress->getValue()];
                }
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', 'Ungültige E-Mail-Adresse: '.$e->getMessage());
                $result = ['success' => false, 'email' => $testEmail, 'error' => $e->getMessage()];
            } catch (\Exception $e) {
                $this->addFlash('error', 'Fehler: '.$e->getMessage());
                $result = ['success' => false, 'email' => $testEmail, 'error' => $e->getMessage()];
            }
        }

        return $this->render('admin/test_reminders.html.twig', [
            'test_email' => $testEmail,
            'result' => $result,
        ]);
    }

    /**
     * Sendet alle fälligen Erinnerungen manuell (Admin-Trigger).
     *
     * @param Request $request HTTP-Request mit CSRF-Token
     *
     * @return Response Redirect zur Testseite
     */
    #[Route('/send-all-reminders', name: 'app_admin_send_all_reminders', methods: ['POST'])]
    public function sendAllReminders(Request $request): Response
    {
        if ($this->isCsrfTokenValid('send_reminders', $request->request->get('_token'))) {
            try {
                $stats = $this->adminService->sendAllReminders();

                $message = sprintf(
                    'Erinnerungen versendet: %d erfolgreich, %d fehlgeschlagen, %d übersprungen, %d Eskalationen',
                    $stats['sent'],
                    $stats['failed'],
                    $stats['skipped'],
                    $stats['escalations']
                );

                if ($stats['sent'] > 0 || $stats['escalations'] > 0) {
                    $this->addFlash('success', $message);
                } else {
                    $this->addFlash('info', 'Keine Erinnerungen zu versenden. '.$message);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Fehler beim Senden der Erinnerungen: '.$e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Ungültiger CSRF-Token.');
        }

        return $this->redirectToRoute('app_admin_test_reminders');
    }
}
