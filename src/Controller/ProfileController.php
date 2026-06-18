<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\CollectionStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        CollectionStats $stats,
        BadgeService $badges,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $profileForm = $this->createForm(ProfileType::class, $user);
        $profileForm->handleRequest($request);
        if ($profileForm->isSubmitted() && $profileForm->isValid()) {
            $users->save($user);
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile');
        }

        // Password change is only relevant for local accounts (SSO users have none).
        $passwordForm = $this->createForm(ChangePasswordType::class);
        $hasLocalPassword = $user->getPassword() !== null;
        if ($hasLocalPassword) {
            $passwordForm->handleRequest($request);
            if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
                $current = (string) $passwordForm->get('currentPassword')->getData();
                if (!$hasher->isPasswordValid($user, $current)) {
                    $this->addFlash('error', 'Mot de passe actuel incorrect.');
                } else {
                    $user->setPassword($hasher->hashPassword($user, (string) $passwordForm->get('newPassword')->getData()));
                    $users->save($user);
                    $this->addFlash('success', 'Mot de passe modifié.');

                    return $this->redirectToRoute('app_profile');
                }
            }
        }

        $progresses = $stats->forCollectedAlbums($user);

        return $this->render('profile/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $hasLocalPassword ? $passwordForm : null,
            'summary' => $stats->summarize($progresses),
            'badges' => $badges->forUser($user),
        ]);
    }

    #[Route('/profile/share', name: 'app_profile_share', methods: ['POST'])]
    public function share(Request $request, UserRepository $users): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('profile-share', (string) $request->request->get('_token'))) {
            if ($request->request->get('action') === 'disable') {
                $user->setShareToken(null);
                $this->addFlash('success', 'Lien public désactivé.');
            } else {
                $user->setShareToken(bin2hex(random_bytes(8)));
                $this->addFlash('success', 'Lien public de partage généré.');
            }
            $users->save($user);
        }

        return $this->redirectToRoute('app_profile');
    }
}
