<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Entity\User;
use App\Entity\UserSticker;
use App\Repository\UserStickerRepository;
use App\Service\CollectionStats;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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

        // Turbo Stream: update just the cell, the album counters and the team header in place.
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $album = $sticker->getAlbum();
            $team = $sticker->getTeam() ?? CollectionStats::UNGROUPED;
            $teamProgress = $stats->teamBreakdown($album, $repo->getQuantityMapForAlbum($user, $album))[$team] ?? null;

            return $this->render('collection/adjust.stream.html.twig', [
                'sticker' => $sticker,
                'qty' => $quantity,
                'album' => $album,
                'progress' => $stats->forAlbum($user, $album),
                'team' => $team,
                'teamProgress' => $teamProgress,
            ]);
        }

        // Fallback (no JS / no Turbo): full-page redirect.
        return $this->back($request, $sticker);
    }

    /**
     * "Pack opening": add a whole batch of stickers at once by typing their numbers
     * (space/comma separated, numeric ranges like "10-15" expand). Each occurrence of
     * a number adds one copy. Updates every touched cell + the album/team progress in
     * one Turbo Stream response.
     */
    #[Route('/album/{slug}/bulk-add', name: 'app_collection_bulk_add', methods: ['POST'])]
    public function bulkAdd(
        Request $request,
        #[MapEntity(mapping: ['slug' => 'slug'])] Album $album,
        UserStickerRepository $repo,
        CollectionStats $stats,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('bulk-add-'.$album->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        // Map "number" → Sticker for this album (numbers are matched case-insensitively).
        $byNumber = [];
        foreach ($album->getStickers() as $sticker) {
            $byNumber[mb_strtolower($sticker->getNumber())] = $sticker;
        }

        $increments = [];   // sticker id => how many copies to add
        $matched = [];      // sticker id => Sticker
        $unknown = [];      // tokens that match no sticker in this album
        foreach ($this->parseNumbers((string) $request->request->get('numbers', '')) as $token) {
            $sticker = $byNumber[mb_strtolower($token)] ?? null;
            if ($sticker === null) {
                $unknown[$token] = true;
                continue;
            }
            $id = $sticker->getId();
            $increments[$id] = ($increments[$id] ?? 0) + 1;
            $matched[$id] = $sticker;
        }

        $updated = [];
        foreach ($increments as $id => $inc) {
            $sticker = $matched[$id];
            $holding = $repo->findOneByUserAndSticker($user, $sticker)
                ?? (new UserSticker())->setUser($user)->setSticker($sticker)->setQuantity(0);
            $holding->setQuantity($holding->getQuantity() + $inc);
            $repo->save($holding, false);
            $updated[] = ['sticker' => $sticker, 'qty' => $holding->getQuantity()];
        }
        $em->flush();

        $addedCount = array_sum($increments);
        $unknownList = array_keys($unknown);

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            // Recompute the team breakdown once, keep only the teams we touched.
            $breakdown = $stats->teamBreakdown($album, $repo->getQuantityMapForAlbum($user, $album));
            $teams = [];
            foreach ($updated as $u) {
                $team = $u['sticker']->getTeam() ?? CollectionStats::UNGROUPED;
                $teams[$team] = $breakdown[$team] ?? null;
            }

            return $this->render('collection/bulk_add.stream.html.twig', [
                'album' => $album,
                'updated' => $updated,
                'progress' => $stats->forAlbum($user, $album),
                'teams' => $teams,
                'feedback' => ['added' => $addedCount, 'unknown' => $unknownList],
            ]);
        }

        if ($addedCount > 0) {
            $this->addFlash('success', $addedCount.' vignette(s) ajoutée(s).');
        }
        if ($unknownList !== []) {
            $this->addFlash('error', 'Numéros inconnus : '.implode(', ', $unknownList));
        }

        return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
    }

    /**
     * @return string[] sticker-number tokens, with repetitions; "a-b" expands to a range
     */
    private function parseNumbers(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', trim($raw)) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            if (preg_match('/^(\d+)-(\d+)$/', $token, $m) === 1) {
                $a = (int) $m[1];
                $b = (int) $m[2];
                if ($a <= $b && ($b - $a) <= 500) {
                    for ($i = $a; $i <= $b; ++$i) {
                        $out[] = (string) $i;
                    }
                }
                continue;
            }
            $out[] = $token;
        }

        return $out;
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
