<?php

namespace App\Controller;

use App\Repository\TradeProposalRepository;
use App\Repository\UserAlbumRepository;
use App\Repository\UserRepository;
use App\Repository\UserStickerRepository;
use App\Service\BadgeService;
use App\Service\Reputation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

class PublicCollectionController extends AbstractController
{
    #[Route('/u/{token}', name: 'app_public_collection', methods: ['GET'])]
    public function show(
        string $token,
        UserRepository $users,
        UserAlbumRepository $userAlbums,
        UserStickerRepository $userStickers,
        BadgeService $badges,
        TradeProposalRepository $trades,
    ): Response {
        $owner = $users->findOneBy(['shareToken' => $token]);
        if ($owner === null) {
            throw $this->createNotFoundException();
        }

        $albums = [];
        foreach ($userAlbums->findByUser($owner) as $userAlbum) {
            $album = $userAlbum->getAlbum();
            $map = $userStickers->getQuantityMapForAlbum($owner, $album);

            $missing = [];
            $duplicates = [];
            foreach ($album->getStickers() as $sticker) {
                $qty = $map[$sticker->getId()] ?? 0;
                if ($qty < 1) {
                    $missing[$sticker->getTeam() ?? 'Divers'][] = $sticker;
                } elseif ($qty > 1) {
                    $duplicates[$sticker->getTeam() ?? 'Divers'][] = ['sticker' => $sticker, 'spare' => $qty - 1];
                }
            }

            $albums[] = [
                'album' => $album,
                'missing' => $missing,
                'duplicates' => $duplicates,
                'missingCount' => array_sum(array_map('count', $missing)),
                'duplicatesCount' => array_sum(array_map('count', $duplicates)),
            ];
        }

        $response = $this->render('public/collection.html.twig', [
            'owner' => $owner,
            'albums' => $albums,
            'badges' => $badges->earnedForUser($owner),
            'reputation' => new Reputation($trades->countCompletedFor($owner)),
        ]);

        // Public, anonymous page → cacheable (benefits from the nginx FastCGI
        // micro-cache and any shared HTTP cache). No per-user data here.
        $response->setPublic();
        $response->setMaxAge(60);
        $response->setSharedMaxAge(60);
        // Keep the response public even though reading flash messages starts the
        // session (Symfony would otherwise force Cache-Control: private).
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }
}
