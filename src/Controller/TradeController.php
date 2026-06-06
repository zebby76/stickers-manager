<?php

namespace App\Controller;

use App\Entity\TradeProposal;
use App\Entity\TradeProposalItem;
use App\Entity\User;
use App\Enum\TradeDirection;
use App\Enum\TradeStatus;
use App\Repository\StickerRepository;
use App\Repository\TradeProposalRepository;
use App\Repository\UserRepository;
use App\Service\TradeManager;
use App\Service\TradeMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/trades')]
class TradeController extends AbstractController
{
    #[Route('', name: 'app_trade_index', methods: ['GET'])]
    public function index(TradeMatcher $matcher, TradeProposalRepository $proposals): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('trade/index.html.twig', [
            'matches' => $matcher->findMatches($user),
            'incoming' => $proposals->findIncoming($user),
            'outgoing' => $proposals->findOutgoing($user),
        ]);
    }

    #[Route('/with/{id}', name: 'app_trade_with', methods: ['GET'])]
    public function with(User $other, TradeMatcher $matcher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($other === $user) {
            return $this->redirectToRoute('app_trade_index');
        }

        return $this->render('trade/with.html.twig', [
            'match' => $matcher->match($user, $other),
        ]);
    }

    #[Route('/with/{id}', name: 'app_trade_create', methods: ['POST'])]
    public function create(
        Request $request,
        User $other,
        StickerRepository $stickers,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('trade-create-'.$other->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_trade_with', ['id' => $other->getId()]);
        }

        $giveIds = array_map('intval', (array) $request->request->all('give'));
        $receiveIds = array_map('intval', (array) $request->request->all('receive'));

        if (!$giveIds && !$receiveIds) {
            $this->addFlash('error', 'Sélectionne au moins une vignette à échanger.');

            return $this->redirectToRoute('app_trade_with', ['id' => $other->getId()]);
        }

        $proposal = (new TradeProposal())
            ->setFromUser($user)
            ->setToUser($other)
            ->setMessage(trim((string) $request->request->get('message')) ?: null);

        foreach ($giveIds as $id) {
            if ($sticker = $stickers->find($id)) {
                $proposal->addItem((new TradeProposalItem())->setSticker($sticker)->setDirection(TradeDirection::Give));
            }
        }
        foreach ($receiveIds as $id) {
            if ($sticker = $stickers->find($id)) {
                $proposal->addItem((new TradeProposalItem())->setSticker($sticker)->setDirection(TradeDirection::Receive));
            }
        }

        $em->persist($proposal);
        $em->flush();

        $this->addFlash('success', 'Proposition envoyée à '.$other->getDisplayName().' !');

        return $this->redirectToRoute('app_trade_show', ['id' => $proposal->getId()]);
    }

    #[Route('/{id}', name: 'app_trade_show', methods: ['GET'])]
    public function show(TradeProposal $proposal): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$proposal->involves($user)) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('trade/show.html.twig', [
            'proposal' => $proposal,
            'isRecipient' => $proposal->getToUser() === $user,
        ]);
    }

    #[Route('/{id}/respond', name: 'app_trade_respond', methods: ['POST'])]
    public function respond(
        Request $request,
        TradeProposal $proposal,
        EntityManagerInterface $em,
        TradeManager $tradeManager,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$proposal->involves($user)) {
            throw $this->createAccessDeniedException();
        }

        $action = (string) $request->request->get('action');
        if (!$this->isCsrfTokenValid('trade-respond-'.$proposal->getId(), (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_trade_show', ['id' => $proposal->getId()]);
        }

        $isRecipient = $proposal->getToUser() === $user;
        $isInitiator = $proposal->getFromUser() === $user;

        match ($action) {
            'accept' => $this->guard($isRecipient && $proposal->getStatus() === TradeStatus::Pending,
                fn () => $proposal->setStatus(TradeStatus::Accepted), 'Proposition acceptée.'),
            'decline' => $this->guard($isRecipient && $proposal->getStatus() === TradeStatus::Pending,
                fn () => $proposal->setStatus(TradeStatus::Declined), 'Proposition refusée.'),
            'cancel' => $this->guard($isInitiator && $proposal->getStatus()->isOpen(),
                fn () => $proposal->setStatus(TradeStatus::Cancelled), 'Proposition annulée.'),
            'complete' => $this->guard($proposal->getStatus() === TradeStatus::Accepted,
                fn () => $tradeManager->complete($proposal), 'Échange finalisé : les collections ont été mises à jour ! 🎉'),
            default => null,
        };

        $em->flush();

        return $this->redirectToRoute('app_trade_show', ['id' => $proposal->getId()]);
    }

    private function guard(bool $allowed, callable $action, string $successMessage): void
    {
        if ($allowed) {
            $action();
            $this->addFlash('success', $successMessage);
        } else {
            $this->addFlash('error', "Action impossible dans l'état actuel de la proposition.");
        }
    }
}
