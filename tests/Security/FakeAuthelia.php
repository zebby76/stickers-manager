<?php

namespace App\Tests\Security;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Stand-in for Authelia's back-channel endpoints (token + userinfo), plugged in as
 * the Guzzle *handler* of the OAuth2 client in the test env. Everything above the
 * socket — league/oauth2-client, Guzzle's middleware stack, PSR-7 — runs for real;
 * only the network hop is replaced.
 *
 * State is static because KernelBrowser reboots the kernel (and so rebuilds this
 * service) between requests; call reset() in setUp().
 */
final class FakeAuthelia
{
    /** @var list<RequestInterface> */
    public static array $requests = [];

    /** @var array<string, mixed> */
    public static array $claims = [];

    /** Response for the token endpoint; null = a valid bearer token. */
    public static ?Response $tokenResponse = null;

    public static function reset(): void
    {
        self::$requests = [];
        self::$claims = ['sub' => 'authelia-sub-1', 'email' => 'sso@example.com', 'name' => 'SSO User'];
        self::$tokenResponse = null;
    }

    public function __invoke(RequestInterface $request, array $options): PromiseInterface
    {
        self::$requests[] = $request;
        $json = ['Content-Type' => 'application/json'];

        $response = match ($request->getUri()->getPath()) {
            '/api/oidc/token' => self::$tokenResponse ?? new Response(200, $json, json_encode([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'id_token' => 'unused.by.the.app',
            ])),
            '/api/oidc/userinfo' => new Response(200, $json, json_encode(self::$claims)),
            default => new Response(404),
        };

        return Create::promiseFor($response);
    }
}
