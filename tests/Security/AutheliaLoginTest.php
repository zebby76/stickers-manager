<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use GuzzleHttp\Psr7\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The Authelia OIDC return leg (/connect/authelia/check): state check, PKCE code
 * exchange and userinfo over the real league/oauth2-client + Guzzle stack, against
 * FakeAuthelia. DB changes are rolled back by DAMA after each test.
 */
class AutheliaLoginTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        FakeAuthelia::reset();
        $this->client = static::createClient();
    }

    /**
     * Hits /connect/authelia and returns the query of the authorization redirect
     * (state, code_challenge, …), as Authelia would receive it.
     *
     * @return array<string, string>
     */
    private function startLogin(): array
    {
        $this->client->request('GET', '/connect/authelia');
        self::assertResponseStatusCodeSame(302);
        $location = $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://auth.zebbox.net/api/oidc/authorization?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function returnFromAuthelia(string $state, string $code = 'auth-code-123'): void
    {
        $this->client->request('GET', '/connect/authelia/check', ['code' => $code, 'state' => $state]);
    }

    private function findUser(array $criteria): ?User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy($criteria);
    }

    public function testNewSsoUserIsCreatedApprovedAndLoggedIn(): void
    {
        $query = $this->startLogin();
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame('stickers-test', $query['client_id']);

        $this->returnFromAuthelia($query['state']);
        self::assertResponseRedirects('/');

        // Token exchange: authorization code + PKCE verifier matching the challenge.
        self::assertCount(2, FakeAuthelia::$requests);
        [$tokenRequest, $userinfoRequest] = FakeAuthelia::$requests;
        self::assertSame('POST', $tokenRequest->getMethod());
        self::assertSame('https://auth.zebbox.net/api/oidc/token', (string) $tokenRequest->getUri());
        parse_str((string) $tokenRequest->getBody(), $form);
        self::assertSame('authorization_code', $form['grant_type']);
        self::assertSame('auth-code-123', $form['code']);
        self::assertSame('http://localhost/connect/authelia/check', $form['redirect_uri']);
        self::assertSame(
            $query['code_challenge'],
            rtrim(strtr(base64_encode(hash('sha256', $form['code_verifier'], true)), '+/', '-_'), '='),
            'PKCE code_verifier must match the code_challenge sent on redirect',
        );

        // Userinfo is fetched with the bearer token from the exchange.
        self::assertSame('https://auth.zebbox.net/api/oidc/userinfo', (string) $userinfoRequest->getUri());
        self::assertSame('Bearer fake-access-token', $userinfoRequest->getHeaderLine('Authorization'));

        $user = $this->findUser(['autheliaId' => 'authelia-sub-1']);
        self::assertNotNull($user, 'SSO login must create the account');
        self::assertSame('sso@example.com', $user->getEmail());
        self::assertSame('SSO User', $user->getDisplayName());
        self::assertTrue($user->isApproved(), 'Authelia accounts are auto-approved');

        // The session is authenticated: the dashboard no longer bounces to /login.
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testExistingLocalAccountIsLinkedByEmail(): void
    {
        $alice = $this->findUser(['email' => 'alice@example.com']);
        self::assertNotNull($alice, 'Fixture user alice@example.com must exist');
        self::assertNull($alice->getAutheliaId());
        FakeAuthelia::$claims = ['sub' => 'alice-sub', 'email' => 'alice@example.com', 'name' => 'Alice SSO'];

        $this->returnFromAuthelia($this->startLogin()['state']);
        self::assertResponseRedirects('/');

        $linked = $this->findUser(['autheliaId' => 'alice-sub']);
        self::assertNotNull($linked);
        self::assertSame($alice->getId(), $linked->getId(), 'Must link the existing account, not create one');
    }

    public function testKnownSubjectReusesItsAccount(): void
    {
        $this->returnFromAuthelia($this->startLogin()['state']);
        $first = $this->findUser(['autheliaId' => 'authelia-sub-1']);
        self::assertNotNull($first);

        // Same subject, e-mail changed on the Authelia side → still the same account.
        $this->client->request('GET', '/logout');
        FakeAuthelia::$claims['email'] = 'renamed@example.com';
        $this->returnFromAuthelia($this->startLogin()['state']);
        self::assertResponseRedirects('/');

        self::assertSame(1, static::getContainer()->get(UserRepository::class)->count(['autheliaId' => 'authelia-sub-1']));
        self::assertNull($this->findUser(['email' => 'renamed@example.com']));
    }

    public function testForgedStateIsRejectedBeforeAnyBackChannelCall(): void
    {
        $this->startLogin();
        $this->returnFromAuthelia('forged-state');

        self::assertResponseRedirects('/login');
        self::assertSame([], FakeAuthelia::$requests, 'No token exchange may happen with an invalid state');
        self::assertNull($this->findUser(['autheliaId' => 'authelia-sub-1']));
    }

    public function testTokenEndpointErrorSendsBackToLogin(): void
    {
        FakeAuthelia::$tokenResponse = new Response(400, ['Content-Type' => 'application/json'], json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'The authorization code has expired.',
        ]));

        $this->returnFromAuthelia($this->startLogin()['state']);

        self::assertResponseRedirects('/login');
        self::assertCount(1, FakeAuthelia::$requests, 'Userinfo must not be called without a token');
        self::assertNull($this->findUser(['autheliaId' => 'authelia-sub-1']));
        $this->client->followRedirect();
        self::assertSelectorExists('.alert');
    }
}
