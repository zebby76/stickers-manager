<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Enum\StickerRarity;
use App\Repository\StickerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class StickerController extends AbstractController
{
    #[Route('/albums/{slug}/stickers/add', name: 'app_sticker_add', methods: ['POST'])]
    public function add(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Album $album, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('sticker-add-'.$album->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        $number = trim((string) $request->request->get('number'));

        if ($number === '') {
            $this->addFlash('error', "L'identifiant de la vignette est obligatoire.");

            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        $sticker = (new Sticker())
            ->setAlbum($album)
            ->setNumber($number)
            ->setTeam(($t = trim((string) $request->request->get('team'))) !== '' ? $t : null)
            ->setRarity(StickerRarity::tryFrom((string) $request->request->get('rarity')) ?? StickerRarity::Common)
            ->setPosition($this->nextPosition($album));

        $em->persist($sticker);
        $em->flush();

        $this->addFlash('success', 'Vignette ajoutée.');

        return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
    }

    /**
     * Bulk import: one sticker per line, fields separated by ";".
     * Format: number ; team (optional) ; rarity (optional: common|shiny|badge|legend)
     */
    #[Route('/albums/{slug}/stickers/import', name: 'app_sticker_import', methods: ['POST'])]
    public function import(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Album $album, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('sticker-import-'.$album->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        $existing = [];
        foreach ($album->getStickers() as $s) {
            $existing[$s->getNumber()] = true;
        }

        $position = $this->nextPosition($album);
        $added = 0;
        $skipped = 0;

        foreach (preg_split('/\r\n|\r|\n/', (string) $request->request->get('bulk')) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode(';', $line));
            $number = $parts[0] ?? '';

            if ($number === '' || isset($existing[$number])) {
                ++$skipped;
                continue;
            }

            $sticker = (new Sticker())
                ->setAlbum($album)
                ->setNumber($number)
                ->setTeam(($parts[1] ?? '') !== '' ? $parts[1] : null)
                ->setRarity(StickerRarity::tryFrom($parts[2] ?? '') ?? StickerRarity::Common)
                ->setPosition($position++);

            $em->persist($sticker);
            $existing[$number] = true;
            ++$added;
        }

        $em->flush();
        $this->addFlash('success', sprintf('%d vignette(s) importée(s), %d ignorée(s).', $added, $skipped));

        return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
    }

    #[Route('/stickers/{id}/delete', name: 'app_sticker_delete', methods: ['POST'])]
    public function delete(Request $request, Sticker $sticker, StickerRepository $repo, EntityManagerInterface $em): Response
    {
        $album = $sticker->getAlbum();

        if ($this->isCsrfTokenValid('sticker-delete-'.$sticker->getId(), (string) $request->request->get('_token'))) {
            $em->remove($sticker);
            $em->flush();
            $this->addFlash('success', 'Vignette supprimée.');
        }

        return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
    }

    private function nextPosition(Album $album): int
    {
        $max = 0;
        foreach ($album->getStickers() as $s) {
            $max = max($max, $s->getPosition());
        }

        return $max + 1;
    }
}
