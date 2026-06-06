<?php

namespace App\Controller;

use App\Entity\Sticker;
use App\Entity\User;
use App\Entity\UserSticker;
use App\Repository\UserStickerRepository;
use App\Service\CollectionStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Turbo\TurboBundle;

#[IsGranted('ROLE_USER')]
#[Route('/collection')]
class CollectionController extends AbstractController
{
    #[Route('/sticker/{id}/adjust', name: 'app_collection_adjust', methods: ['POST'])]
    public function adjust(
        Request $request,
        Sticker $sticker,
        UserStickerRepository $repo,
        CollectionStats $stats,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('adjust-'.$sticker->getId(), (string) $request->request->get('_token'))) {
            return $this->back($request, $sticker);
        }

        $delta = (int) $request->request->get('delta', 0);
        $holding = $repo->findOneByUserAndSticker($user, $sticker);

        if ($holding === null && $delta > 0) {
            $holding = (new UserSticker())->setUser($user)->setSticker($sticker)->setQuantity(0);
        }

        if ($holding !== null) {
            $holding->setQuantity($holding->getQuantity() + $delta);

            if ($holding->getQuantity() === 0) {
                if ($holding->getId() !== null) {
                    $repo->remove($holding);
                }
            } else {
                $repo->save($holding);
            }
        }

        $quantity = $holding?->getQuantity() ?? 0;

        // Turbo Stream: update just the cell and the progress counters in place.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            return $this->render('collection/adjust.stream.html.twig', [
                'sticker' => $sticker,
                'qty' => $quantity,
                'album' => $sticker->getAlbum(),
                'progress' => $stats->forAlbum($user, $sticker->getAlbum()),
            ]);
        }

        // Fallback (no JS / no Turbo): full-page redirect.
        return $this->back($request, $sticker);
    }

    private function back(Request $request, Sticker $sticker): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_album_show', ['slug' => $sticker->getAlbum()->getSlug()]);
    }
}
