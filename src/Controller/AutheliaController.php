<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AutheliaController extends AbstractController
{
    #[Route('/connect/authelia', name: 'connect_authelia_start', methods: ['GET'])]
    public function connect(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry->getClient('authelia')->redirect(['openid', 'profile', 'email', 'groups'], []);
    }

    /**
     * Authelia redirects here after consent. The authentication itself is handled
     * by App\Security\AutheliaAuthenticator on this route.
     */
    #[Route('/connect/authelia/check', name: 'connect_authelia_check', methods: ['GET'])]
    public function check(): Response
    {
        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
