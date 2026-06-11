<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Logs users in via the self-hosted Authelia SSO (OIDC, auth.zebbox.net).
 * Mirrors the old GoogleAuthenticator, but accounts coming from Authelia are
 * auto-approved: identities are already curated in Authelia, so no second
 * admin validation in the app.
 */
class AutheliaAuthenticator extends OAuth2Authenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_authelia_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('authelia');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client): User {
                $owner = $client->fetchUserFromToken($accessToken);
                $claims = $owner->toArray();
                $sub = (string) $owner->getId();
                $repo = $this->em->getRepository(User::class);

                // 1) Already linked to this Authelia subject.
                if ($existing = $repo->findOneBy(['autheliaId' => $sub])) {
                    return $existing;
                }

                $email = (string) ($claims['email'] ?? '');

                // 2) Local account with the same e-mail → link it to Authelia.
                if ($email !== '' && $byEmail = $repo->findOneBy(['email' => $email])) {
                    $byEmail->setAutheliaId($sub);
                    $this->em->flush();

                    return $byEmail;
                }

                // 3) Brand-new account → auto-approved (curated in Authelia).
                $name = $claims['name'] ?? $claims['preferred_username'] ?? null;
                if (!$name) {
                    $name = $email !== '' ? explode('@', $email)[0] : $sub;
                }
                $user = (new User())
                    ->setEmail($email !== '' ? $email : $sub.'@authelia.local')
                    ->setAutheliaId($sub)
                    ->setDisplayName(mb_substr((string) $name, 0, 60))
                    ->setApproved(true)
                    ->setActive(true);
                $this->em->persist($user);
                $this->em->flush();

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return new RedirectResponse($this->router->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', strtr($exception->getMessageKey(), $exception->getMessageData()));

        return new RedirectResponse($this->router->generate('app_login'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate('app_login'));
    }
}
