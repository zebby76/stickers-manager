<?php

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[UniqueEntity(fields: ['slug'], message: 'Un album existe déjà avec cet identifiant.')]
class Album
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 160, unique: true)]
    #[Assert\NotBlank]
    private ?string $slug = null;

    #[ORM\Column(length: 80)]
    private string $publisher = 'Stickers';

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1960, max: 2100)]
    private ?int $year = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Average number of stickers contained in one pack — used to estimate how
     * many packs are needed to complete the album.
     */
    #[ORM\Column]
    #[Assert\Positive]
    private int $stickersPerPack = 5;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Sticker> */
    #[ORM\OneToMany(targetEntity: Sticker::class, mappedBy: 'album', orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $stickers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->stickers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPublisher(): string
    {
        return $this->publisher;
    }

    public function setPublisher(string $publisher): static
    {
        $this->publisher = $publisher;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStickersPerPack(): int
    {
        return $this->stickersPerPack;
    }

    public function setStickersPerPack(int $stickersPerPack): static
    {
        $this->stickersPerPack = $stickersPerPack;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Sticker>
     */
    public function getStickers(): Collection
    {
        return $this->stickers;
    }

    public function addSticker(Sticker $sticker): static
    {
        if (!$this->stickers->contains($sticker)) {
            $this->stickers->add($sticker);
            $sticker->setAlbum($this);
        }

        return $this;
    }

    public function removeSticker(Sticker $sticker): static
    {
        $this->stickers->removeElement($sticker);

        return $this;
    }

    public function getTotalStickers(): int
    {
        return $this->stickers->count();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
