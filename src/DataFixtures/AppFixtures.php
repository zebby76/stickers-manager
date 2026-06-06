<?php

namespace App\DataFixtures;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Entity\User;
use App\Entity\UserAlbum;
use App\Entity\UserSticker;
use App\Enum\StickerRarity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // —— Users (password: "password") ————————————————————————————————————
        // Alice is an admin (manages the album catalog); Bob & Carol are regular
        // collectors.
        $users = [];
        foreach ([
            ['alice@example.com', 'Alice', 'FR', ['ROLE_ADMIN'], true],
            ['bob@example.com', 'Bob', 'BE', [], true],
            ['carol@example.com', 'Carol', 'IT', [], true],
            // Pending registration awaiting admin approval (cannot log in yet).
            ['dave@example.com', 'Dave', 'NL', [], false],
        ] as [$email, $name, $country, $roles, $approved]) {
            $user = (new User())
                ->setEmail($email)
                ->setDisplayName($name)
                ->setCountry($country)
                ->setRoles($roles)
                ->setApproved($approved);
            $user->setPassword($this->hasher->hashPassword($user, 'password'));
            $manager->persist($user);
            $users[$name] = $user;
        }

        // —— Album: World Cup 2022 ———————————————————————————————————————————
        $wc = (new Album())
            ->setName('FIFA World Cup Qatar 2022')
            ->setSlug('world-cup-2022')
            ->setPublisher('Stickers')
            ->setYear(2022)
            ->setStickersPerPack(5)
            ->setDescription('Album officiel de la Coupe du Monde 2022.');
        $manager->persist($wc);

        $teams = [
            'Argentina' => ['Lionel Messi', 'Emiliano Martínez', 'Ángel Di María', 'Julián Álvarez', 'Enzo Fernández'],
            'France' => ['Kylian Mbappé', 'Antoine Griezmann', 'Olivier Giroud', 'Aurélien Tchouaméni', 'Hugo Lloris'],
            'Brazil' => ['Neymar Jr', 'Vinícius Jr', 'Casemiro', 'Alisson', 'Richarlison'],
            'Belgium' => ['Kevin De Bruyne', 'Romelu Lukaku', 'Thibaut Courtois', 'Eden Hazard'],
        ];

        $position = 1;
        $stickers = [];
        foreach ($teams as $team => $players) {
            // One shiny badge per team.
            $badge = (new Sticker())
                ->setAlbum($wc)
                ->setNumber('B'.$position)
                ->setTeam($team)
                ->setRarity(StickerRarity::Badge)
                ->setPosition($position++);
            $manager->persist($badge);
            $stickers[] = $badge;

            foreach ($players as $i => $player) {
                $sticker = (new Sticker())
                    ->setAlbum($wc)
                    ->setNumber((string) $position)
                    ->setTeam($team)
                    ->setRarity($i === 0 ? StickerRarity::Shiny : StickerRarity::Common)
                    ->setPosition($position++);
                $manager->persist($sticker);
                $stickers[] = $sticker;
            }
        }

        // —— Album: Euro 2024 (smaller) ——————————————————————————————————————
        $euro = (new Album())
            ->setName('UEFA Euro 2024')
            ->setSlug('euro-2024')
            ->setPublisher('Stickers')
            ->setYear(2024)
            ->setStickersPerPack(5);
        $manager->persist($euro);
        $euroStickers = [];
        foreach (range(1, 6) as $i) {
            $s = (new Sticker())
                ->setAlbum($euro)
                ->setNumber((string) $i)
                ->setTeam('Stars')
                ->setRarity(StickerRarity::Common)
                ->setPosition($i + 1);
            $manager->persist($s);
            $euroStickers[] = $s;
        }

        // —— Collections & holdings ——————————————————————————————————————————
        // Everyone collects the World Cup album.
        foreach ($users as $user) {
            $manager->persist((new UserAlbum())->setUser($user)->setAlbum($wc));
        }
        // Alice & Bob also collect Euro 2024.
        $manager->persist((new UserAlbum())->setUser($users['Alice'])->setAlbum($euro));
        $manager->persist((new UserAlbum())->setUser($users['Bob'])->setAlbum($euro));

        // Alice: owns first 12 WC stickers, with duplicates on a few.
        $this->give($manager, $users['Alice'], \array_slice($stickers, 0, 12), duplicatesEvery: 3);
        // Bob: owns WC stickers 6..20, duplicates on some — complements Alice.
        $this->give($manager, $users['Bob'], \array_slice($stickers, 6, 14), duplicatesEvery: 4);
        // Carol: owns a scattered subset with many duplicates.
        $this->give($manager, $users['Carol'], \array_slice($stickers, 2, 10), duplicatesEvery: 2);

        // Euro holdings to create cross-album matches between Alice and Bob.
        $this->give($manager, $users['Alice'], \array_slice($euroStickers, 0, 3), duplicatesEvery: 1);
        $this->give($manager, $users['Bob'], \array_slice($euroStickers, 2, 4), duplicatesEvery: 1);

        $manager->flush();
    }

    /**
     * @param Sticker[] $stickers
     */
    private function give(ObjectManager $manager, User $user, array $stickers, int $duplicatesEvery): void
    {
        foreach ($stickers as $i => $sticker) {
            $quantity = ($duplicatesEvery > 0 && ($i % $duplicatesEvery) === 0) ? 3 : 1;
            $manager->persist(
                (new UserSticker())->setUser($user)->setSticker($sticker)->setQuantity($quantity)
            );
        }
    }
}
