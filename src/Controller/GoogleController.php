<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('google')->redirect(['email', 'profile'], []);
    }

    /**
     * Google redirects here after consent. The authentication itself is handled
     * by App\Security\GoogleAuthenticator on this route.
     */
    #[Route('/connect/google/check', name: 'connect_google_check', methods: ['GET'])]
    public function check(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
