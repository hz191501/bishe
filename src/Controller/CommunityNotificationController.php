<?php

/*
 * 社区通知控制器：显示当前用户收到的通知，并处理通知分页和已读状态。
 */

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommunityNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifiche')]
final class CommunityNotificationController extends AbstractController
{
    
    #[Route('', name: 'app_community_notification_index', methods: ['GET'])]
    public function index(Request $request, CommunityNotificationRepository $repository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 15;
        $notifications = $repository->findPaginatedFor($user, $page, $perPage);
        $totalItems = count($notifications);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $repository->markAllAsRead($user);

        if ($page > $totalPages && $totalItems > 0) {
            return $this->redirectToRoute('app_community_notification_index', ['page' => $totalPages]);
        }

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
            ],
        ]);
    }
}
