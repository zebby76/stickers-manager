<?php

namespace App\Controller;

use App\Entity\TradeProposal;
use App\Enum\TradeStatus;
use App\Repository\TradeProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/trades')]
class AdminTradeController extends AbstractController
{
    #[Route('', name: 'app_admin_trades', methods: ['GET'])]
    public function index(Request $request, TradeProposalRepository $proposals): Response
    {
        $status = TradeStatus::tryFrom((string) $request->query->get('status', ''));

        return $this->render('admin/trades.html.twig', [
            'proposals' => $proposals->findAllForAdmin($status),
            'counts' => $proposals->countsByStatus(),
            'currentStatus' => $status,
            'statuses' => TradeStatus::cases(),
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_trade_cancel', methods: ['POST'])]
    public function cancel(Request $request, TradeProposal $proposal, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin-trade-cancel-'.$proposal->getId(), (string) $request->request->get('_token'))) {
            if ($proposal->getStatus()->isOpen()) {
                $proposal->setStatus(TradeStatus::Cancelled);
                $em->flush();
                $this->addFlash('success', 'Proposition #'.$proposal->getId().' annulée.');
            } else {
                $this->addFlash('error', 'Seules les propositions en cours peuvent être annulées.');
            }
        }

        return $this->redirectToRefererOr($request);
    }

    #[Route('/{id}/delete', name: 'app_admin_trade_delete', methods: ['POST'])]
    public function delete(Request $request, TradeProposal $proposal, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('admin-trade-delete-'.$proposal->getId(), (string) $request->request->get('_token'))) {
            $id = $proposal->getId();
            $em->remove($proposal);
            $em->flush();
            $this->addFlash('success', 'Proposition #'.$id.' supprimée définitivement.');
        }

        return $this->redirectToRefererOr($request);
    }

    private function redirectToRefererOr(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer !== null && str_contains($referer, '/admin/trades')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_admin_trades');
    }
}
