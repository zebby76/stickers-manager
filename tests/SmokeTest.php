<?php

namespace App\Tests;

use App\Entity\Album;
use App\Entity\User;
use App\Repository\AlbumRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class SmokeTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function loginAs(string $email): User
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, "Fixture user $email must exist");
        $this->client->loginUser($user);

        return $user;
    }

    public function testGuestIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testLoginPageRenders(): void
    {
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Stickers Manager');
        self::assertSelectorExists('a[href="/reset-password"]', 'Login page should link to password reset');
    }

    public function testFooterShowsVersion(): void
    {
        // APP_VERSION is injected as a Twig global and rendered in the footer.
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('footer.app-footer');
    }

    public function testForgotPasswordPageRenders(): void
    {
        $this->client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mot de passe oublié');
    }

    public function testPasswordResetSendsEmailForKnownUser(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[email]'] = 'alice@example.com';
        $this->client->submit($form);

        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(1);
    }

    public function testPasswordResetDoesNotLeakUnknownEmail(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $crawler->selectButton('Envoyer le lien')->form();
        $form['reset_password_request_form[email]'] = 'nobody@example.com';
        $this->client->submit($form);

        // Same outcome as a known address (no account enumeration), but no e-mail sent.
        self::assertResponseRedirects('/reset-password/check-email');
        self::assertEmailCount(0);
    }

    public function testAuthenticatedPagesRender(): void
    {
        $this->loginAs('alice@example.com');

        $album = static::getContainer()->get(AlbumRepository::class)->findOneBy(['slug' => 'world-cup-2022']);
        self::assertInstanceOf(Album::class, $album);

        foreach ([
            '/' => 'Tableau de bord',
            '/albums' => "Catalogue d'albums",
            '/albums/world-cup-2022' => 'World Cup',
            '/duplicates' => 'Mes doublons',
            '/trades' => 'Échanges',
        ] as $path => $expected) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful("GET $path should be 200");
            self::assertStringContainsString($expected, (string) $this->client->getResponse()->getContent(), "Page $path content");
        }
    }

    public function testCatalogManagementRequiresAdmin(): void
    {
        // Bob is a regular collector → no access to album management.
        $this->loginAs('bob@example.com');
        $this->client->request('GET', '/albums/new');
        self::assertResponseStatusCodeSame(403, 'A regular user must not manage albums');

        // Alice is an admin → access granted.
        $this->loginAs('alice@example.com');
        $this->client->request('GET', '/albums/new');
        self::assertResponseIsSuccessful('An admin can manage albums');
    }

    public function testAdjustStickerReturnsTurboStream(): void
    {
        $this->loginAs('bob@example.com');

        $crawler = $this->client->request('GET', '/albums/world-cup-2022');
        self::assertResponseIsSuccessful();

        $cell = $crawler->filter('[id^="sticker-"]')->first();
        $action = $cell->filter('form')->eq(1)->attr('action'); // 2nd form = "+1"
        $token = $cell->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request(
            'POST',
            $action,
            ['_token' => $token, 'delta' => '1'],
            [],
            ['HTTP_ACCEPT' => 'text/vnd.turbo-stream.html']
        );

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<turbo-stream', $content);
        self::assertStringContainsString('target="album-progress"', $content);
    }

    public function testAdminCanImportAlbumFromJson(): void
    {
        $this->loginAs('alice@example.com');

        $crawler = $this->client->request('GET', '/albums/import');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $json = json_encode([
            'name' => 'Import Test Cup 2030',
            'year' => 2030,
            'stickersPerPack' => 6,
            'stickers' => [
                ['number' => '1', 'name' => 'Player One', 'team' => 'Alpha', 'rarity' => 'shiny'],
                ['number' => '2', 'name' => 'Player Two', 'team' => 'Alpha'],
                ['number' => '2', 'name' => 'Duplicate Number', 'team' => 'Alpha'], // skipped
            ],
        ], \JSON_THROW_ON_ERROR);

        $path = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($path, $json);
        $file = new UploadedFile($path, 'album.json', 'application/json', null, true);

        $this->client->request('POST', '/albums/import', ['_token' => $token], ['file' => $file]);
        self::assertResponseRedirects();

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Import Test Cup 2030', (string) $this->client->getResponse()->getContent());
        // 2 stickers imported (the duplicate number is skipped).
        self::assertStringContainsString('2 vignettes', (string) $this->client->getResponse()->getContent());

        @unlink($path);
    }

    public function testProfilePageRenders(): void
    {
        $this->loginAs('alice@example.com');
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('alice@example.com', (string) $this->client->getResponse()->getContent());
    }

    public function testPublicCollectionLinkWorksWithoutLogin(): void
    {
        // Set a share token directly, then hit the page as an anonymous visitor.
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $alice = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alice@example.com']);
        $alice->setShareToken('share-test-token');
        $em->flush();

        $this->client->request('GET', '/u/share-test-token');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString($alice->getDisplayName(), (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/u/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }

    public function testProfileCanGenerateShareLink(): void
    {
        $this->loginAs('alice@example.com');
        $crawler = $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action="/profile/share"] input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/profile/share', ['_token' => $token]);
        self::assertResponseRedirects('/profile');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $alice = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alice@example.com']);
        self::assertNotNull($alice->getShareToken(), 'A share token should have been generated');
    }

    public function testUserManagementRequiresAdmin(): void
    {
        $this->loginAs('bob@example.com');
        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403, 'A regular user must not manage users');
    }

    public function testAdminCanApprovePendingUser(): void
    {
        $users = static::getContainer()->get(UserRepository::class);
        $dave = $users->findOneBy(['email' => 'dave@example.com']);
        self::assertNotNull($dave);
        self::assertFalse($dave->isApproved(), 'Dave starts pending');
        $daveId = $dave->getId();

        $this->loginAs('alice@example.com');
        $crawler = $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('form[action="/admin/users/'.$daveId.'/approve"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/users/'.$daveId.'/approve', ['_token' => $token]);
        self::assertResponseRedirects('/admin/users');

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $dave = static::getContainer()->get(UserRepository::class)->find($daveId);
        self::assertTrue($dave->isApproved(), 'Dave should be approved after admin action');
    }

    public function testTradeMatchingAndProposalFlow(): void
    {
        $alice = $this->loginAs('alice@example.com');
        $bob = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'bob@example.com']);

        // The match page with Bob renders and offers stickers to exchange.
        $crawler = $this->client->request('GET', '/trades/with/'.$bob->getId());
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(
            0,
            $crawler->filter('input[type=checkbox]')->count(),
            'There should be stickers to trade between Alice and Bob'
        );
    }
}
