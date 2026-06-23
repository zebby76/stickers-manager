<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\User;
use App\Entity\UserAlbum;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use App\Repository\TradeProposalRepository;
use App\Repository\UserAlbumRepository;
use App\Repository\UserStickerRepository;
use App\Service\AlbumImporter;
use App\Service\CollectionStats;
use App\Service\Reputation;
use App\Service\TradeMatcher;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('ROLE_USER')]
#[Route('/albums')]
class AlbumController extends AbstractController
{
    #[Route('', name: 'app_album_index', methods: ['GET'])]
    public function index(
        AlbumRepository $albums,
        UserAlbumRepository $userAlbums,
        CollectionStats $stats,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $collectedIds = [];
        foreach ($userAlbums->findByUser($user) as $ua) {
            $collectedIds[$ua->getAlbum()->getId()] = true;
        }

        $all = $albums->findAllOrdered();
        $rows = [];
        foreach ($all as $album) {
            $collected = isset($collectedIds[$album->getId()]);
            $rows[] = [
                'album' => $album,
                'collected' => $collected,
                'progress' => $collected ? $stats->forAlbum($user, $album) : null,
            ];
        }

        return $this->render('album/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/new', name: 'app_album_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, AlbumRepository $albums, SluggerInterface $slugger): Response
    {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $album->setSlug($this->uniqueSlug($albums, $slugger, $album->getName()));
            $albums->save($album);
            $this->addFlash('success', 'Album créé. Ajoute maintenant ses vignettes.');

            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('album/new.html.twig', ['form' => $form]);
    }

    #[Route('/import', name: 'app_album_import', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function import(Request $request, AlbumImporter $importer): Response
    {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('album-import', (string) $request->request->get('_token'))) {
                return $this->redirectToRoute('app_album_import');
            }

            $file = $request->files->get('file');
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                $this->addFlash('error', 'Merci de sélectionner un fichier valide.');

                return $this->redirectToRoute('app_album_import');
            }

            $content = (string) file_get_contents($file->getPathname());
            $extension = strtolower($file->getClientOriginalExtension());
            $isJson = $extension === 'json' || str_starts_with(ltrim($content), '{');

            try {
                $result = $isJson
                    ? $importer->importJson($content)
                    : $importer->importCsv($content, (string) $request->request->get('name', ''));
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Import impossible : '.$e->getMessage());

                return $this->redirectToRoute('app_album_import');
            }

            $this->addFlash('success', sprintf(
                'Album « %s » importé avec %d vignette(s).',
                $result['album']->getName(),
                $result['imported'],
            ));

            return $this->redirectToRoute('app_album_show', ['slug' => $result['album']->getSlug()]);
        }

        return $this->render('album/import.html.twig');
    }

    #[Route('/{slug}', name: 'app_album_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Album $album,
        UserStickerRepository $userStickers,
        UserAlbumRepository $userAlbums,
        CollectionStats $stats,
        TradeMatcher $matcher,
        TradeProposalRepository $proposals,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $quantities = $userStickers->getQuantityMapForAlbum($user, $album);
        $collected = $userAlbums->findOneByUserAndAlbum($user, $album) !== null;

        // Trade radar: other collectors who hold spares I'm still missing here.
        $radar = $matcher->albumRadar($user, $album);
        $repCounts = $proposals->completedCountsForUsers(array_map(static fn (array $r): int => $r['user']->getId(), $radar));
        $reputations = array_map(static fn (int $n): Reputation => new Reputation($n), $repCounts);

        // Group stickers by team for display.
        $groups = [];
        foreach ($album->getStickers() as $sticker) {
            $groups[$sticker->getTeam() ?? CollectionStats::UNGROUPED][] = $sticker;
        }

        // Non-country sections come first, then countries alphabetically (accent-aware).
        $sectionOrder = ['Ouverture' => 0, 'Palmarès' => 1, CollectionStats::UNGROUPED => 99];
        $collator = new \Collator('fr_FR');
        uksort($groups, static function (string $a, string $b) use ($sectionOrder, $collator): int {
            $pa = $sectionOrder[$a] ?? 50;
            $pb = $sectionOrder[$b] ?? 50;

            return $pa !== $pb ? $pa <=> $pb : $collator->compare($a, $b);
        });

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'groups' => $groups,
            'quantities' => $quantities,
            'teamProgress' => $stats->teamBreakdown($album, $quantities),
            'collected' => $collected,
            'progress' => $stats->forAlbum($user, $album),
            'radar' => $radar,
            'reputations' => $reputations,
        ]);
    }

    #[Route('/{slug}/edit', name: 'app_album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Album $album, AlbumRepository $albums): Response
    {
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $albums->save($album);
            $this->addFlash('success', 'Album mis à jour.');

            return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('album/edit.html.twig', ['form' => $form, 'album' => $album]);
    }

    #[Route('/{slug}/delete', name: 'app_album_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Album $album, AlbumRepository $albums): Response
    {
        if ($this->isCsrfTokenValid('delete-album-'.$album->getId(), (string) $request->request->get('_token'))) {
            $albums->remove($album);
            $this->addFlash('success', 'Album supprimé.');
        }

        return $this->redirectToRoute('app_album_index');
    }

    #[Route('/{slug}/collect', name: 'app_album_collect', methods: ['POST'])]
    public function toggleCollect(Request $request, #[MapEntity(mapping: ['slug' => 'slug'])] Album $album, UserAlbumRepository $userAlbums): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('collect-'.$album->getId(), (string) $request->request->get('_token'))) {
            $existing = $userAlbums->findOneByUserAndAlbum($user, $album);
            if ($existing) {
                $userAlbums->remove($existing);
                $this->addFlash('success', 'Album retiré de ta collection.');
            } else {
                $ua = (new UserAlbum())->setUser($user)->setAlbum($album);
                $userAlbums->save($ua);
                $this->addFlash('success', 'Album ajouté à ta collection !');
            }
        }

        return $this->redirectToRoute('app_album_show', ['slug' => $album->getSlug()]);
    }

    private function uniqueSlug(AlbumRepository $albums, SluggerInterface $slugger, string $name): string
    {
        $base = strtolower($slugger->slug($name)->toString());
        $slug = $base;
        $i = 2;
        while ($albums->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
