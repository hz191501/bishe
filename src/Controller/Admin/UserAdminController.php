<?php

/*
 * 用户管理控制器：仅供管理员查看用户列表、分页搜索和调整管理员角色。
 */

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]

final class UserAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $query = trim($request->query->getString('q'));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 10;
        $builder = $userRepository->createQueryBuilder('user')
            ->orderBy('user.createdAt', 'DESC');

        if ($query !== '') {
            $builder
                ->andWhere('LOWER(user.email) LIKE :query OR LOWER(user.username) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        $users = new Paginator(
            $builder
                ->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage)
                ->getQuery()
        );
        $totalItems = count($users);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        if ($page > $totalPages && $totalItems > 0) {
            return $this->redirectToRoute('app_admin_user_index', array_filter([
                'q' => $query ?: null,
                'page' => $totalPages,
            ]));
        }

        $administratorCount = (int) $userRepository->createQueryBuilder('administrator')
            ->select('COUNT(administrator.id)')
            ->andWhere('administrator.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'query' => $query,
            'userCount' => $userRepository->count([]),
            'administratorCount' => $administratorCount,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
            ],
        ]);
    }

    #[Route('/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function changeRole(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('change_admin_role'.$user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF non valido.');
        }

        $isAdministrator = in_array('ROLE_ADMIN', $user->getRoles(), true);

        if ($user === $this->getUser() && $isAdministrator) {
            $this->addFlash('warning', 'Non puoi rimuovere il tuo ruolo di amministratore.');
        } else {
            $user->setRoles($isAdministrator ? [] : ['ROLE_ADMIN']);
            $entityManager->flush();
            $this->addFlash(
                'success',
                $isAdministrator
                    ? sprintf('%s ora è un utente normale.', $user->getUsername())
                    : sprintf('%s ora è un amministratore.', $user->getUsername())
            );
        }

        return $this->redirectToRoute('app_admin_user_index', array_filter([
            'q' => $request->getPayload()->getString('q') ?: null,
            'page' => max(1, $request->getPayload()->getInt('page', 1)),
        ]), Response::HTTP_SEE_OTHER);
    }
}
