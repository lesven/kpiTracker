<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Controller für Authentifizierung (Login/Logout)
 * User Story 1: Benutzer können sich einloggen.
 */
class SecurityController extends AbstractController
{
    /**
     * Login-Seite anzeigen und Login-Versuche verarbeiten.
     */
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Bereits eingeloggte Benutzer zum Dashboard weiterleiten
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Letzten Login-Fehler abrufen falls vorhanden
        $error = $authenticationUtils->getLastAuthenticationError();

        // Letzten eingegebenen Benutzernamen abrufen
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Logout wird von Symfony Security automatisch verarbeitet.
     */
    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Diese Methode kann leer bleiben - Symfony übernimmt das Logout
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
