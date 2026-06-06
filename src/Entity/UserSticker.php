<?php

namespace App\Entity;

use App\Repository\UserStickerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A user's holding of a sticker. quantity >= 1 means owned; (quantity - 1) is
 * the number of duplicates available for trade.
 */
#[ORM\Entity(repositoryClass: UserStickerRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_user_sticker', columns: ['user_id', 'sticker_id'])]
class UserSticker
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userStickers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Sticker $sticker = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSticker(): ?Sticker
    {
        return $this->sticker;
    }

    public function setSticker(?Sticker $sticker): static
    {
        $this->sticker = $sticker;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = max(0, $quantity);

        return $this;
    }

    public function isOwned(): bool
    {
        return $this->quantity >= 1;
    }

    public function getDuplicates(): int
    {
        return max(0, $this->quantity - 1);
    }
}
