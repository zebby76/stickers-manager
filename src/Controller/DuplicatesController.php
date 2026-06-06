<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserStickerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DuplicatesController extends AbstractController
{
    #[Route('/duplicates', name: 'app_duplicates', methods: ['GET'])]
    public function index(UserStickerRepository $userStickers): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $duplicates = $userStickers->findDuplicates($user);

        // Group by album for readability.
        $byAlbum = [];
        $totalSpare = 0;
        foreach ($duplicates as $holding) {
            $album = $holding->getSticker()->getAlbum();
            $byAlbum[$album->getName()]['album'] = $album;
            $byAlbum[$album->getName()]['items'][] = $holding;
            $totalSpare += $holding->getDuplicates();
        }
        ksort($byAlbum);

        return $this->render('duplicates/index.html.twig', [
            'byAlbum' => $byAlbum,
            'totalSpare' => $totalSpare,
        ]);
    }
}
