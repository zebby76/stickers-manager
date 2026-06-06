<?php

namespace App\Service;

use App\Entity\Sticker;
use App\Entity\TradeProposal;
use App\Entity\User;
use App\Entity\UserSticker;
use App\Enum\TradeDirection;
use App\Enum\TradeStatus;
use Doctrine\ORM\EntityManagerInterface;

class TradeManager
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Apply an accepted trade to both collections, then mark it completed.
     * Give items move from the initiator to the recipient; receive items move
     * the other way.
     */
    public function complete(TradeProposal $proposal): void
    {
        $from = $proposal->getFromUser();
        $to = $proposal->getToUser();

        foreach ($proposal->getItems() as $item) {
            $sticker = $item->getSticker();
            $qty = $item->getQuantity();

            if ($item->getDirection() === TradeDirection::Give) {
                $this->move($from, $to, $sticker, $qty);
            } else {
                $this->move($to, $from, $sticker, $qty);
            }
        }

        $proposal->setStatus(TradeStatus::Completed);
        $this->em->flush();
    }

    private function move(User $giver, User $receiver, Sticker $sticker, int $qty): void
    {
        $giverHolding = $this->holding($giver, $sticker);
        $receiverHolding = $this->holding($receiver, $sticker);

        // Do not let a giver go below zero.
        $effective = min($qty, $giverHolding->getQuantity());
        if ($effective <= 0) {
            return;
        }

        $giverHolding->setQuantity($giverHolding->getQuantity() - $effective);
        $receiverHolding->setQuantity($receiverHolding->getQuantity() + $effective);
    }

    private function holding(User $user, Sticker $sticker): UserSticker
    {
        $repo = $this->em->getRepository(UserSticker::class);
        $holding = $repo->findOneBy(['user' => $user, 'sticker' => $sticker]);

        if ($holding === null) {
            $holding = (new UserSticker())->setUser($user)->setSticker($sticker)->setQuantity(0);
            $this->em->persist($holding);
        }

        return $holding;
    }
}
