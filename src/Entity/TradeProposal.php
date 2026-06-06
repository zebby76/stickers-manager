<?php

namespace App\Entity;

use App\Enum\TradeDirection;
use App\Enum\TradeStatus;
use App\Repository\TradeProposalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TradeProposalRepository::class)]
class TradeProposal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** The user who created the proposal. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $fromUser = null;

    /** The user the proposal is sent to. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $toUser = null;

    #[ORM\Column(enumType: TradeStatus::class)]
    private TradeStatus $status = TradeStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, TradeProposalItem> */
    #[ORM\OneToMany(targetEntity: TradeProposalItem::class, mappedBy: 'proposal', orphanRemoval: true, cascade: ['persist'])]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $fromUser): static
    {
        $this->fromUser = $fromUser;

        return $this;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $toUser): static
    {
        $this->toUser = $toUser;

        return $this;
    }

    public function getStatus(): TradeStatus
    {
        return $this->status;
    }

    public function setStatus(TradeStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, TradeProposalItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(TradeProposalItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setProposal($this);
        }

        return $this;
    }

    public function removeItem(TradeProposalItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }

    /**
     * @return TradeProposalItem[]
     */
    public function getGivenItems(): array
    {
        return $this->items->filter(
            fn (TradeProposalItem $i) => $i->getDirection() === TradeDirection::Give
        )->getValues();
    }

    /**
     * @return TradeProposalItem[]
     */
    public function getReceivedItems(): array
    {
        return $this->items->filter(
            fn (TradeProposalItem $i) => $i->getDirection() === TradeDirection::Receive
        )->getValues();
    }

    public function involves(User $user): bool
    {
        return $this->fromUser === $user || $this->toUser === $user;
    }
}
