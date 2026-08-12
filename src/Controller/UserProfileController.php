<?php

/*
 * 用户资料控制器：显示公开资料、个人统计，并处理资料和密码修改。
 */

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\UserProfileType;
use App\Repository\BridgeTaskRepository;
use App\Repository\BuddyConnectionRepository;
use App\Repository\TaskResponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profilo')]

final class UserProfileController extends AbstractController
{
    #[Route('/modifica', name: 'app_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Il profilo è stato aggiornato.');

            return $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);
        }

        return $this->render('profile/edit.html.twig', ['form' => $form]);
    }

    #[Route('/password', name: 'app_profile_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = (string) $form->get('currentPassword')->getData();

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(
                    new \Symfony\Component\Form\FormError('La password attuale non è corretta.')
                );
            } else {
                $newPassword = (string) $form->get('newPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $entityManager->flush();
                $this->addFlash('success', 'La password è stata modificata correttamente.');

                return $this->redirectToRoute('app_profile_show', ['id' => $user->getId()]);
            }
        }

        return $this->render('profile/password.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_profile_show', methods: ['GET'])]
    public function show(
        User $user,
        Request $request,
        BridgeTaskRepository $taskRepository,
        TaskResponseRepository $responseRepository,
        BuddyConnectionRepository $connectionRepository,
    ): Response {
        $viewer = $this->getUser();
        $connection = $viewer instanceof User && $viewer !== $user
            ? $connectionRepository->findBetween($viewer, $user)
            : null;

        $receivedLikeCount = 0;
        foreach ($user->getBridgeTasks() as $task) {
            $receivedLikeCount += $task->getLikeCount();
        }
        foreach ($user->getTaskResponses() as $response) {
            $receivedLikeCount += $response->getLikeCount();
        }
        foreach ($user->getResponseComments() as $comment) {
            $receivedLikeCount += $comment->getLikeCount();
        }

        // 个人主页的两个列表分别分页，切换其中一页不会影响另一个列表。
        $perPage = 6;
        $publishedPage = max(1, $request->query->getInt('published_page', 1));
        $participatedPage = max(1, $request->query->getInt('participated_page', 1));
        $publishedTasks = $taskRepository->findPublishedByUserPaginated($user, $publishedPage, $perPage);
        $participatedTasks = $taskRepository->findParticipatedByUserPaginated(
            $user,
            $participatedPage,
            $perPage
        );
        $taskCount = count($publishedTasks);
        $participatedTaskCount = count($participatedTasks);

        return $this->render('profile/show.html.twig', [
            'profile_user' => $user,
            'connection' => $connection,
            'tasks' => $publishedTasks,
            'participated_tasks' => $participatedTasks,
            'task_count' => $taskCount,
            'participated_task_count' => $participatedTaskCount,
            'published_pagination' => [
                'currentPage' => $publishedPage,
                'totalPages' => max(1, (int) ceil($taskCount / $perPage)),
                'totalItems' => $taskCount,
            ],
            'participated_pagination' => [
                'currentPage' => $participatedPage,
                'totalPages' => max(1, (int) ceil($participatedTaskCount / $perPage)),
                'totalItems' => $participatedTaskCount,
            ],
            'response_count' => $responseRepository->count(['author' => $user]),
            'best_answer_count' => $responseRepository->count(['author' => $user, 'isBestAnswer' => true]),
            'received_like_count' => $receivedLikeCount,
        ]);
    }
}
