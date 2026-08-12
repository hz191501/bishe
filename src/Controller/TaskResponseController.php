<?php

/*
 * 回答控制器：负责发布、修改和删除帖子回答，
 * 也负责由帖子作者选择或取消“最佳回答”。
 */

namespace App\Controller;

use App\Entity\BridgeTask;
use App\Entity\TaskResponse;
use App\Entity\User;
use App\Form\TaskResponseType;
use App\Service\CommunityNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/risposte')]

final class TaskResponseController extends AbstractController
{
    #[Route('/richiesta/{id}/nuova', name: 'app_task_response_new', methods: ['GET', 'POST'])]
    public function new(
        BridgeTask $bridgeTask,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');



        if ($bridgeTask->getAuthor() === $this->getUser()) {
            $this->addFlash('info', 'Non puoi rispondere alla tua richiesta. Puoi modificarla per aggiungere dettagli.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()]);
        }

        $taskResponse = new TaskResponse();
        $form = $this->createForm(TaskResponseType::class, $taskResponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $author = $this->getUser();
            if (!$author instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $taskResponse
                ->setAuthor($author)
                ->setTask($bridgeTask)
                ->setIsBestAnswer(false)
                ->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($taskResponse);
            $notificationService->create(
                $bridgeTask->getAuthor(),
                $author,
                'new_answer',
                'ha risposto alla tua richiesta.',
                '/bridge/task/'.$bridgeTask->getId(),
            );
            $entityManager->flush();
            $this->addFlash('success', 'La risposta è stata pubblicata.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()]);
        }

        return $this->render('task_response/new.html.twig', [
            'task_response' => $taskResponse,
            'bridge_task' => $bridgeTask,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/modifica', name: 'app_task_response_edit', methods: ['GET', 'POST'])]
    public function edit(TaskResponse $taskResponse, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($taskResponse->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Puoi modificare soltanto le tue risposte.');
        }

        $form = $this->createForm(TaskResponseType::class, $taskResponse);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taskResponse->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'La risposta è stata aggiornata.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $taskResponse->getTask()->getId()]);
        }

        return $this->render('task_response/edit.html.twig', [
            'task_response' => $taskResponse,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/migliore', name: 'app_task_response_best', methods: ['POST'])]
    public function best(
        TaskResponse $taskResponse,
        Request $request,
        EntityManagerInterface $entityManager,
        CommunityNotificationService $notificationService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $bridgeTask = $taskResponse->getTask();

        if ($bridgeTask->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Solo l’autore della richiesta può scegliere la risposta migliore.');
        }

        if ($bridgeTask->getStatus() !== 'open') {
            $this->addFlash('info', 'Questa richiesta è già stata risolta.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()]);
        }

        if (!$this->isCsrfTokenValid('best'.$taskResponse->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }

        foreach ($bridgeTask->getResponses() as $response) {
            $response->setIsBestAnswer(false);
        }

        $taskResponse->setIsBestAnswer(true);
        $bridgeTask->setStatus('resolved')->setResolvedAt(new \DateTimeImmutable());
        $notificationService->create(
            $taskResponse->getAuthor(),
            $bridgeTask->getAuthor(),
            'best_answer',
            'ha scelto la tua risposta come migliore.',
            '/bridge/task/'.$bridgeTask->getId().'#response-'.$taskResponse->getId(),
        );
        $entityManager->flush();
        $this->addFlash('success', 'La richiesta è stata risolta.');

        return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()]);
    }

    #[Route('/{id}', name: 'app_task_response_delete', methods: ['POST'])]
    public function delete(TaskResponse $taskResponse, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if ($taskResponse->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Puoi eliminare soltanto le tue risposte.');
        }

        $task = $taskResponse->getTask();

        if (!$this->isCsrfTokenValid('delete'.$taskResponse->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('warning', 'La risposta non è stata eliminata: token di sicurezza non valido.');
        } else {
            if ($taskResponse->isBestAnswer()) {
                $task->setStatus('open')->setResolvedAt(null);
            }

            $entityManager->remove($taskResponse);
            $entityManager->flush();
            $this->addFlash('success', 'La risposta è stata eliminata.');
        }

        return $this->redirectToRoute('app_bridge_task_show', ['id' => $task->getId()]);
    }
}
