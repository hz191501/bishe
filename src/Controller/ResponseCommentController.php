<?php

/*
 * 回答评论控制器：在回答下新增或删除评论，
 * 同时检查登录、作者权限和 CSRF 安全令牌。
 */

namespace App\Controller;

use App\Entity\ResponseComment;
use App\Entity\TaskResponse;
use App\Entity\User;
use App\Service\CommunityNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commenti-risposta')]

final class ResponseCommentController extends AbstractController
{
    #[Route('/risposta/{id}', name: 'app_response_comment_new', methods: ['POST'])]
    public function new(
        TaskResponse $taskResponse,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->isCsrfTokenValid('comment_'.$taskResponse->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        $content = trim($request->getPayload()->getString('content'));

        if (mb_strlen($content) < 2) {
            $this->addFlash('warning', 'Scrivi un commento di almeno 2 caratteri.');
        } elseif (mb_strlen($content) > 1000) {
            $this->addFlash('warning', 'Il commento non può superare 1000 caratteri.');
        } else {
            $author = $this->getUser();
            if (!$author instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $comment = (new ResponseComment())
                ->setContent($content)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setAuthor($author)
                ->setResponse($taskResponse);

            $entityManager->persist($comment);
            $notificationService->create(
                $taskResponse->getAuthor(),
                $author,
                'new_comment',
                'ha commentato la tua risposta.',
                '/bridge/task/'.$taskResponse->getTask()->getId().'#response-'.$taskResponse->getId(),
            );
            $entityManager->flush();
            $this->addFlash('success', 'Il commento è stato pubblicato.');
        }

        return $this->redirectToRoute('app_bridge_task_show', [
            'id' => $taskResponse->getTask()->getId(),
            '_fragment' => 'response-'.$taskResponse->getId(),
        ]);
    }

    #[Route('/{id}', name: 'app_response_comment_delete', methods: ['POST'])]
    public function delete(ResponseComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($comment->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Puoi eliminare soltanto i tuoi commenti.');
        }

        if (!$this->isCsrfTokenValid('delete_comment'.$comment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        $taskId = $comment->getResponse()->getTask()->getId();
        $responseId = $comment->getResponse()->getId();
        $entityManager->remove($comment);
        $entityManager->flush();
        $this->addFlash('success', 'Il commento è stato eliminato.');

        return $this->redirectToRoute('app_bridge_task_show', [
            'id' => $taskId,
            '_fragment' => 'response-'.$responseId,
        ]);
    }
}
