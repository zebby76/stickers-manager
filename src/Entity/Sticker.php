<?php

namespace App\Entity;

use App\Enum\StickerRarity;
use App\Repository\StickerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StickerRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_sticker_album_number', columns: ['album_id', 'number'])]
class Sticker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Album::class, inversedBy: 'stickers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Album $album = null;

    /**
     * Printed sticker number/code (e.g. "1", "FWC12", "BEL3"). Kept as a string
     * because Stickers numbering is not always purely numeric.
     */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank]
    private ?string $number = null;

    /**
     * Grouping label inside the album (national team, club, section…), used to
     * organize the checklist in the UI.
     */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $team = null;

    #[ORM\Column(enumType: StickerRarity::class)]
    private StickerRarity $rarity = StickerRarity::Common;

    /**
     * Ordering position within the album (defaults follow insertion order).
     */
    #[ORM\Column]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlbum(): ?Album
    {
        return $this->album;
    }

    public function setAlbum(?Album $album): static
    {
        $this->album = $album;

        return $this;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getTeam(): ?string
    {
        return $this->team;
    }

    public function setTeam(?string $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getRarity(): StickerRarity
    {
        return $this->rarity;
    }

    public function setRarity(StickerRarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): string
    {
        return '#'.$this->number;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
