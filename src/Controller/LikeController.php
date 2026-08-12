<?php

/*
 * 点赞控制器：处理帖子、回答和评论的点赞或取消点赞。
 * 每次操作都会检查登录状态与 CSRF 安全令牌。
 */

namespace App\Controller;

use App\Entity\BridgeTask;
use App\Entity\ResponseComment;
use App\Entity\TaskResponse;
use App\Entity\User;
use App\Service\CommunityNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mi-piace')]

final class LikeController extends AbstractController
{
    #[Route('/richiesta/{id}', name: 'app_bridge_task_like', methods: ['POST'])]
    public function toggleTask(
        BridgeTask $bridgeTask,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('like_task'.$bridgeTask->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        $targetPath = '/bridge/task/'.$bridgeTask->getId();

        if ($bridgeTask->getAuthor() === $user) {
            $this->addFlash('info', 'Non puoi mettere Mi piace alla tua richiesta.');
        } elseif ($bridgeTask->isLikedBy($user)) {
            $bridgeTask->removeLike($user);
            $notificationService->remove($bridgeTask->getAuthor(), $user, 'task_like', $targetPath);
        } else {
            $bridgeTask->addLike($user);
            $notificationService->create(
                $bridgeTask->getAuthor(),
                $user,
                'task_like',
                'ha messo Mi piace alla tua richiesta.',
                $targetPath,
            );
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()]);
    }

    #[Route('/risposta/{id}', name: 'app_task_response_like', methods: ['POST'])]
    public function toggleResponse(
        TaskResponse $taskResponse,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('like_response'.$taskResponse->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        $targetPath = '/bridge/task/'.$taskResponse->getTask()->getId().'#response-'.$taskResponse->getId();

        if ($taskResponse->getAuthor() === $user) {
            $this->addFlash('info', 'Non puoi mettere Mi piace alla tua risposta.');
        } elseif ($taskResponse->isLikedBy($user)) {
            $taskResponse->removeLike($user);
            $notificationService->remove($taskResponse->getAuthor(), $user, 'response_like', $targetPath);
        } else {
            $taskResponse->addLike($user);
            $notificationService->create(
                $taskResponse->getAuthor(),
                $user,
                'response_like',
                'ha messo Mi piace alla tua risposta.',
                $targetPath,
            );
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_bridge_task_show', [
            'id' => $taskResponse->getTask()->getId(),
            '_fragment' => 'response-'.$taskResponse->getId(),
        ]);
    }

    #[Route('/commento/{id}', name: 'app_response_comment_like', methods: ['POST'])]
    public function toggleComment(
        ResponseComment $comment,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $user = $this->requireUser();

        if (!$this->isCsrfTokenValid('like_comment'.$comment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        $response = $comment->getResponse();
        $targetPath = '/bridge/task/'.$response->getTask()->getId().'#response-'.$response->getId();

        if ($comment->getAuthor() === $user) {
            $this->addFlash('info', 'Non puoi mettere Mi piace al tuo commento.');
        } elseif ($comment->isLikedBy($user)) {
            $comment->removeLike($user);
            $notificationService->remove($comment->getAuthor(), $user, 'comment_like', $targetPath);
        } else {
            $comment->addLike($user);
            $notificationService->create(
                $comment->getAuthor(),
                $user,
                'comment_like',
                'ha messo Mi piace al tuo commento.',
                $targetPath,
            );
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_bridge_task_show', [
            'id' => $response->getTask()->getId(),
            '_fragment' => 'response-'.$response->getId(),
        ]);
    }

    private function requireUser(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
