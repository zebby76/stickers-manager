<?php

namespace App\Entity;

use App\Enum\TradeDirection;
use App\Repository\TradeProposalItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TradeProposalItemRepository::class)]
class TradeProposalItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TradeProposal $proposal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Sticker $sticker = null;

    #[ORM\Column(enumType: TradeDirection::class)]
    private TradeDirection $direction = TradeDirection::Give;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProposal(): ?TradeProposal
    {
        return $this->proposal;
    }

    public function setProposal(?TradeProposal $proposal): static
    {
        $this->proposal = $proposal;

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

    public function getDirection(): TradeDirection
    {
        return $this->direction;
    }

    public function setDirection(TradeDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }
}
