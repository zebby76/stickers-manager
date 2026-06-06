<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CollectionStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(CollectionStats $stats): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $progresses = $stats->forCollectedAlbums($user);
        $summary = $stats->summarize($progresses);

        return $this->render('dashboard/index.html.twig', [
            'progresses' => $progresses,
            'summary' => $summary,
        ]);
    }
}
