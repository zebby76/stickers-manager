<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\Sticker;
use App\Enum\StickerRarity;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Imports a full album (metadata + stickers) from a JSON document, or a list of
 * stickers from a CSV document.
 */
class AlbumImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlbumRepository $albums,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * JSON shape:
     * {
     *   "name": "...", "publisher": "Stickers", "year": 2022,
     *   "stickersPerPack": 5, "description": "...",
     *   "stickers": [ {"number","name","team","rarity"}, ... ]
     * }
     *
     * @return array{album: Album, imported: int}
     */
    public function importJson(string $content): array
    {
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data) || !isset($data['name']) || trim((string) $data['name']) === '') {
            throw new \InvalidArgumentException('Le JSON doit contenir au moins un champ "name".');
        }

        $album = (new Album())
            ->setName(trim((string) $data['name']))
            ->setSlug($this->uniqueSlug((string) $data['name']))
            ->setPublisher(trim((string) ($data['publisher'] ?? 'Stickers')) ?: 'Stickers')
            ->setYear(isset($data['year']) ? (int) $data['year'] : null)
            ->setStickersPerPack(max(1, (int) ($data['stickersPerPack'] ?? 5)))
            ->setDescription(($d = trim((string) ($data['description'] ?? ''))) !== '' ? $d : null);

        $this->em->persist($album);

        $imported = 0;
        $position = 1;
        $seen = [];
        foreach ((array) ($data['stickers'] ?? []) as $row) {
            if (!\is_array($row)) {
                continue;
            }
            if ($this->addSticker($album, $seen, $position, [
                (string) ($row['number'] ?? ''),
                (string) ($row['team'] ?? ''),
                (string) ($row['rarity'] ?? ''),
            ])) {
                ++$imported;
            }
        }

        $this->em->flush();

        return ['album' => $album, 'imported' => $imported];
    }

    /**
     * CSV columns: number, team (optional), rarity (optional). A header row
     * starting with "number"/"n°"/"#" is detected and skipped.
     *
     * @return array{album: Album, imported: int}
     */
    public function importCsv(string $content, string $albumName): array
    {
        if (trim($albumName) === '') {
            throw new \InvalidArgumentException("Un nom d'album est requis pour un import CSV.");
        }

        $album = (new Album())
            ->setName(trim($albumName))
            ->setSlug($this->uniqueSlug($albumName));
        $this->em->persist($album);

        $imported = 0;
        $position = 1;
        $seen = [];
        $first = true;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line);
            $cols = array_map(static fn ($c) => trim((string) $c), $cols);

            if ($first) {
                $first = false;
                $head = strtolower($cols[0] ?? '');
                if (\in_array($head, ['number', 'n°', 'no', '#', 'numero', 'numéro'], true)) {
                    continue; // skip header row
                }
            }

            if ($this->addSticker($album, $seen, $position, [
                $cols[0] ?? '',
                $cols[1] ?? '',
                $cols[2] ?? '',
            ])) {
                ++$imported;
            }
        }

        $this->em->flush();

        return ['album' => $album, 'imported' => $imported];
    }

    /**
     * @param array<int, true> $seen     numbers already added (dedupe)
     * @param string[]         $fields   [number, team, rarity]
     */
    private function addSticker(Album $album, array &$seen, int &$position, array $fields): bool
    {
        [$number, $team, $rarity] = $fields;
        $number = trim($number);

        if ($number === '' || isset($seen[$number])) {
            return false;
        }

        $sticker = (new Sticker())
            ->setAlbum($album)
            ->setNumber($number)
            ->setTeam(trim($team) !== '' ? trim($team) : null)
            ->setRarity(StickerRarity::tryFrom(strtolower(trim($rarity))) ?? StickerRarity::Common)
            ->setPosition($position++);

        $this->em->persist($sticker);
        $seen[$number] = true;

        return true;
    }

    private function uniqueSlug(string $name): string
    {
        $base = strtolower($this->slugger->slug($name)->toString());
        $slug = $base;
        $i = 2;
        while ($this->albums->findOneBy(['slug' => $slug]) !== null) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
