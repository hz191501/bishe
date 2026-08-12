<?php

/*
 * 帖子控制器：负责帖子列表、筛选、分页、发布、修改、查看和删除。
 * 这里连接表单、数据库仓库和 Twig 页面，是帖子功能的主要入口。
 */

namespace App\Controller;

use App\Entity\BridgeTask;
use App\Entity\User;
use App\Form\BridgeTaskType;
use App\Repository\BridgeTaskRepository;
use App\Repository\CategoryRepository;
use App\Repository\TaskResponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bridge/task')]

final class BridgeTaskController extends AbstractController
{
    #[Route(name: 'app_bridge_task_index', methods: ['GET'])]
    public function index(
        Request $request,
        BridgeTaskRepository $taskRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        // 从网址查询参数中读取搜索词、状态、分类和页码。
        // max(1, ...) 可以避免用户手动输入第 0 页或负数页码。
        $query = trim($request->query->getString('q'));
        $status = $request->query->getString('status');
        $categoryId = $request->query->getInt('category') ?: null;
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 9;

        // 仓库返回 Doctrine Paginator；count() 得到符合筛选条件的总帖子数。
        $tasks = $taskRepository->searchPaginated($query ?: null, $status ?: null, $categoryId, $page, $perPage);
        $totalItems = count($tasks);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        // 删除数据后可能出现“当前页超过最后一页”，此时自动回到最后一个有效页面。
        if ($page > $totalPages && $totalItems > 0) {
            return $this->redirectToRoute('app_bridge_task_index', array_filter([
                'q' => $query ?: null,
                'status' => $status ?: null,
                'category' => $categoryId,
                'page' => $totalPages,
            ]));
        }

        return $this->render('bridge_task/index.html.twig', [
            'bridge_tasks' => $tasks,
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'filters' => ['q' => $query, 'status' => $status, 'category' => $categoryId],
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
            ],
        ]);
    }

    #[Route('/new', name: 'app_bridge_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // 发布帖子必须先登录；getUser() 返回当前登录用户。
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $task = new BridgeTask();
        $form = $this->createForm(BridgeTaskType::class, $task);
        $form->handleRequest($request);

        // 只有表单已经提交并且通过全部验证时，才写入数据库。
        if ($form->isSubmitted() && $form->isValid()) {
            $task
                ->setAuthor($user)
                ->setStatus('open')
                ->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($task);
            $entityManager->flush();
            $this->addFlash('success', 'La richiesta è stata pubblicata.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('bridge_task/new.html.twig', ['bridge_task' => $task, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_bridge_task_show', methods: ['GET'])]
    public function show(
        BridgeTask $bridgeTask,
        Request $request,
        TaskResponseRepository $responseRepository,
    ): Response
    {
        $responsePage = max(1, $request->query->getInt('response_page', 1));
        $responsePerPage = 10;
        $responseTotalItems = $responseRepository->count(['task' => $bridgeTask]);
        $responseTotalPages = max(1, (int) ceil($responseTotalItems / $responsePerPage));
        $responsePage = min($responsePage, $responseTotalPages);

        return $this->render('bridge_task/show.html.twig', [
            'bridge_task' => $bridgeTask,
            'responses' => $responseRepository->findForTaskOrderedPaginated(
                $bridgeTask,
                $responsePage,
                $responsePerPage,
            ),
            'response_pagination' => [
                'currentPage' => $responsePage,
                'totalPages' => $responseTotalPages,
                'totalItems' => $responseTotalItems,
            ],
        ]);
    }

    #[Route('/{id}/edit', name: 'app_bridge_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, BridgeTask $bridgeTask, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // 普通用户只能修改自己的帖子，管理员可以协助管理全部帖子。
        if ($bridgeTask->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Puoi modificare soltanto le tue richieste.');
        }

        $form = $this->createForm(BridgeTaskType::class, $bridgeTask);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $bridgeTask->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'La richiesta è stata aggiornata.');

            return $this->redirectToRoute('app_bridge_task_show', ['id' => $bridgeTask->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('bridge_task/edit.html.twig', ['bridge_task' => $bridgeTask, 'form' => $form]);
    }

    #[Route('/{id}', name: 'app_bridge_task_delete', methods: ['POST'])]
    public function delete(Request $request, BridgeTask $bridgeTask, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // 删除前先检查作者权限，再验证 CSRF 令牌，防止伪造删除请求。
        if ($bridgeTask->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Puoi eliminare soltanto le tue richieste.');
        }

        if (!$this->isCsrfTokenValid('delete'.$bridgeTask->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('warning', 'La richiesta non è stata eliminata: token di sicurezza non valido.');
        } else {
            $entityManager->remove($bridgeTask);
            $entityManager->flush();
            $this->addFlash('success', 'La richiesta è stata eliminata.');
        }

        return $this->redirectToRoute('app_bridge_task_index', [], Response::HTTP_SEE_OTHER);
    }
}
