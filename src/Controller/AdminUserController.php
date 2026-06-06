<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'app_admin_users', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/users.html.twig', [
            'users' => $users->findAllForAdmin(),
        ]);
    }

    #[Route('/{id}/approve', name: 'app_admin_user_approve', methods: ['POST'])]
    public function approve(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->valid($request, 'approve', $user)) {
            $user->setApproved(true);
            $em->flush();
            $this->addFlash('success', $user->getDisplayName().' a été approuvé·e.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}/ban', name: 'app_admin_user_ban', methods: ['POST'])]
    public function toggleBan(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->valid($request, 'ban', $user) && $this->notSelf($user)) {
            $user->setActive(!$user->isActive());
            $em->flush();
            $this->addFlash('success', $user->getDisplayName().($user->isActive() ? ' a été réactivé·e.' : ' a été désactivé·e.'));
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function toggleAdmin(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->valid($request, 'role', $user) && $this->notSelf($user)) {
            $isAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);

            // Only approved & active accounts are eligible to become admins.
            if (!$isAdmin && (!$user->isApproved() || !$user->isActive())) {
                $this->addFlash('error', 'Seuls les comptes approuvés et actifs peuvent devenir administrateurs.');

                return $this->redirectToRoute('app_admin_users');
            }

            $roles = array_values(array_filter($user->getRoles(), static fn (string $r) => $r !== 'ROLE_USER'));
            if ($isAdmin) {
                $roles = array_values(array_filter($roles, static fn (string $r) => $r !== 'ROLE_ADMIN'));
                $msg = ' n\'est plus administrateur.';
            } else {
                $roles[] = 'ROLE_ADMIN';
                $msg = ' est désormais administrateur.';
            }
            $user->setRoles($roles);
            $em->flush();
            $this->addFlash('success', $user->getDisplayName().$msg);
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->valid($request, 'delete', $user) && $this->notSelf($user)) {
            $name = $user->getDisplayName();
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', $name.' a été supprimé·e définitivement.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    private function valid(Request $request, string $action, User $user): bool
    {
        return $this->isCsrfTokenValid('user-'.$action.'-'.$user->getId(), (string) $request->request->get('_token'));
    }

    private function notSelf(User $user): bool
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Tu ne peux pas effectuer cette action sur ton propre compte.');

            return false;
        }

        return true;
    }
}
