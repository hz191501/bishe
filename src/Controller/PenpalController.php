<?php

/*
 * 好友控制器：负责好友推荐、好友申请、接受或拒绝申请、
 * 好友搜索与分页，以及好友之间的信件页面。
 */

namespace App\Controller;

use App\Entity\BridgeTask;
use App\Entity\BuddyConnection;
use App\Entity\PenpalMessage;
use App\Entity\User;
use App\Repository\BridgeTaskRepository;
use App\Repository\BuddyConnectionRepository;
use App\Repository\PenpalMessageRepository;
use App\Repository\UserRepository;
use App\Service\BridgeJourneyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/amici-di-penna')]
class PenpalController extends AbstractController
{
    #[Route('', name: 'app_penpal_index', methods: ['GET'])]
    public function index(
        Request $request,
        BuddyConnectionRepository $connections,
        UserRepository $users,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();

        // 已经存在申请或好友关系的用户不再出现在推荐名单中。
        $excludedUserIds = [];
        foreach ($user->getSentBuddyConnections() as $connection) {
            $excludedUserIds[] = $connection->getReceiver()->getId();
        }
        foreach ($user->getReceivedBuddyConnections() as $connection) {
            $excludedUserIds[] = $connection->getRequester()->getId();
        }

        $friendQuery = trim($request->query->getString('friend_q'));
        $acceptedConnections = $connections->findAcceptedFor($user);

        // 搜索只在当前用户的好友中进行，可匹配昵称、城市或国籍。
        if ($friendQuery !== '') {
            $normalizedQuery = mb_strtolower($friendQuery);
            $acceptedConnections = array_values(array_filter(
                $acceptedConnections,
                static function (BuddyConnection $connection) use ($user, $normalizedQuery): bool {
                    $friend = $connection->getRequester() === $user
                        ? $connection->getReceiver()
                        : $connection->getRequester();
                    $searchableText = mb_strtolower(implode(' ', array_filter([
                        $friend->getUsername(),
                        $friend->getCity(),
                        $friend->getNationality(),
                    ])));

                    return str_contains($searchableText, $normalizedQuery);
                },
            ));
        }

        $activityDate = static function (BuddyConnection $connection): \DateTimeImmutable {
            $lastMessage = $connection->getMessages()->last();

            return $lastMessage
                ? $lastMessage->getCreatedAt()
                : ($connection->getAcceptedAt() ?? $connection->getCreatedAt() ?? new \DateTimeImmutable('@0'));
        };

        // 排序规则：先显示未读信件较多的好友，再按最近聊天时间排列。
        usort(
            $acceptedConnections,
            static function (BuddyConnection $first, BuddyConnection $second) use ($user, $activityDate): int {
                $unreadComparison = $second->getUnreadMessageCountFor($user)
                    <=> $first->getUnreadMessageCountFor($user);

                return $unreadComparison !== 0
                    ? $unreadComparison
                    : $activityDate($second) <=> $activityDate($first);
            },
        );

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 10;
        $totalItems = count($acceptedConnections);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        // 收到和发出的待处理申请分别分页，避免申请较多时页面过长。
        $requestPerPage = 6;
        $receivedPage = max(1, $request->query->getInt('received_page', 1));
        $sentPage = max(1, $request->query->getInt('sent_page', 1));

        $receivedCriteria = ['receiver' => $user, 'status' => 'pending'];
        $sentCriteria = ['requester' => $user, 'status' => 'pending'];
        $receivedTotalItems = $connections->count($receivedCriteria);
        $sentTotalItems = $connections->count($sentCriteria);
        $receivedTotalPages = max(1, (int) ceil($receivedTotalItems / $requestPerPage));
        $sentTotalPages = max(1, (int) ceil($sentTotalItems / $requestPerPage));

        $receivedPage = min($receivedPage, $receivedTotalPages);
        $sentPage = min($sentPage, $sentTotalPages);

        $receivedRequests = $connections->findBy(
            $receivedCriteria,
            ['createdAt' => 'DESC'],
            $requestPerPage,
            ($receivedPage - 1) * $requestPerPage,
        );
        $sentRequests = $connections->findBy(
            $sentCriteria,
            ['createdAt' => 'DESC'],
            $requestPerPage,
            ($sentPage - 1) * $requestPerPage,
        );

        if ($page > $totalPages && $totalItems > 0) {
            return $this->redirectToRoute('app_penpal_index', array_filter([
                'friend_q' => $friendQuery ?: null,
                'page' => $totalPages,
                'received_page' => $receivedPage,
                'sent_page' => $sentPage,
            ]));
        }

        // 排序和搜索完成后再截取当前页，每页显示 10 位好友。
        $acceptedConnections = array_slice(
            $acceptedConnections,
            ($page - 1) * $perPage,
            $perPage,
        );

        return $this->render('penpal/index.html.twig', [
            'suggested_matches' => $users->findPenpalMatches($user, $excludedUserIds),
            'accepted_connections' => $acceptedConnections,
            'friend_query' => $friendQuery,
            'friendship_pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
            ],
            'received_requests' => $receivedRequests,
            'received_pagination' => [
                'currentPage' => $receivedPage,
                'totalPages' => $receivedTotalPages,
                'totalItems' => $receivedTotalItems,
            ],
            'sent_requests' => $sentRequests,
            'sent_pagination' => [
                'currentPage' => $sentPage,
                'totalPages' => $sentTotalPages,
                'totalItems' => $sentTotalItems,
            ],
        ]);
    }

    #[Route('/richiesta/{id}', name: 'app_penpal_request', methods: ['POST'])]
    public function requestFriend(
        User $receiver,
        Request $request,
        BuddyConnectionRepository $connections,
        BridgeTaskRepository $bridgeTasks,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $requester = $this->getUser();

        $sourceTaskId = $request->request->getInt('source_task_id');
        $sourceTask = $sourceTaskId > 0 ? $bridgeTasks->find($sourceTaskId) : null;

        // 如果好友申请来自某个帖子，只接受帖子作者或实际回答过该帖的用户，
        // 避免用户伪造一个与双方无关的帖子编号。
        if ($sourceTask && $sourceTask->getAuthor() !== $receiver) {
            $receiverAnswered = $sourceTask->getResponses()->exists(
                static fn (int $key, $response): bool => $response->getAuthor() === $receiver,
            );
            if (!$receiverAnswered) {
                $sourceTask = null;
            }
        }
        if (!$this->isCsrfTokenValid('penpal_request_'.$receiver->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($requester === $receiver) {
            $this->addFlash('warning', 'Non puoi inviare una richiesta a te stesso.');
        } elseif ($connections->findBetween($requester, $receiver)) {
            $this->addFlash('info', 'Esiste già un collegamento o una richiesta tra voi.');
        } else {
            $connection = (new BuddyConnection())
                ->setRequester($requester)
                ->setReceiver($receiver)
                ->setStatus('pending')
                ->setCreatedAt(new \DateTimeImmutable())
                ->setSourceTask($sourceTask);
            $entityManager->persist($connection);
            $entityManager->flush();
            $this->addFlash('success', 'Richiesta di amicizia inviata.');
        }

        // 推荐卡片提交申请后留在好友页面；个人资料页提交时仍返回该用户资料。
        if ($request->request->getString('return_to') === 'penpal') {
            return $this->redirectToRoute('app_penpal_index', array_filter([
                'friend_q' => trim($request->request->getString('friend_q')) ?: null,
                'page' => max(1, $request->request->getInt('page', 1)),
                'received_page' => max(1, $request->request->getInt('received_page', 1)),
                'sent_page' => max(1, $request->request->getInt('sent_page', 1)),
            ]));
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $receiver->getId()]);
    }

    #[Route('/{id}/accetta', name: 'app_penpal_accept', methods: ['POST'])]
    public function accept(BuddyConnection $connection, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkDecision($connection, $request, 'accept');
        $connection->setStatus('accepted')->setAcceptedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'Richiesta accettata. Ora potete scrivervi.');

        return $this->redirectToRoute('app_penpal_index');
    }

    #[Route('/{id}/rifiuta', name: 'app_penpal_reject', methods: ['POST'])]
    public function reject(BuddyConnection $connection, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkDecision($connection, $request, 'reject');
        $connection->setStatus('rejected');
        $entityManager->flush();
        $this->addFlash('info', 'Richiesta rifiutata.');

        return $this->redirectToRoute('app_penpal_index');
    }

    #[Route('/{id}/messaggi', name: 'app_penpal_chat', methods: ['GET', 'POST'])]
    public function chat(
        BuddyConnection $connection,
        Request $request,
        EntityManagerInterface $entityManager,
        BridgeJourneyService $journeyService,
        PenpalMessageRepository $messageRepository,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if ($connection->getStatus() !== 'accepted' || ($connection->getRequester() !== $user && $connection->getReceiver() !== $user)) {
            throw $this->createAccessDeniedException();
        }

        $hasUnreadMessages = false;
        foreach ($connection->getMessages() as $message) {
            if ($message->getAuthor() !== $user && !$message->isRead()) {
                $message->setIsRead(true);
                $hasUnreadMessages = true;
            }
        }
        if ($hasUnreadMessages) {
            $entityManager->flush();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('message_'.$connection->getId(), $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $subject = trim((string) $request->request->get('subject'));
            $content = trim((string) $request->request->get('content'));

            if ($content === '') {
                $this->addFlash('warning', 'Scrivi una lettera prima di inviare.');

            } elseif (mb_strlen($content) > 4000) {
                $this->addFlash('warning', 'La lettera non può superare 4000 caratteri.');
            } elseif (mb_strlen($subject) > 150) {
                $this->addFlash('warning', 'L’oggetto non può superare 150 caratteri.');
            } else {
                $message = (new PenpalMessage())
                    ->setConnection($connection)
                    ->setAuthor($user)
                    ->setSubject($subject !== '' ? $subject : null)
                    ->setContent($content)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setIsRead(false);

                $entityManager->persist($message);
                $entityManager->flush();

                return $this->redirectToRoute('app_penpal_chat', ['id' => $connection->getId()]);
            }
        }

        $friend = $connection->getRequester() === $user
            ? $connection->getReceiver()
            : $connection->getRequester();

        if (!$connection->getSourceTask()) {
            $sourceTask = $this->inferSourceTask($user, $friend, $connection->getCreatedAt());
            if ($sourceTask) {
                $connection->setSourceTask($sourceTask);
                $entityManager->flush();
            }
        }

        $archive = $journeyService->buildConnectionJourney($connection);

        // 默认打开最后一页，让用户进入页面时直接看到最新信件。
        $messagePerPage = 30;
        $messageTotalItems = $messageRepository->count(['connection' => $connection]);
        $messageTotalPages = max(1, (int) ceil($messageTotalItems / $messagePerPage));
        $requestedMessagePage = $request->query->getInt('message_page', 0);
        $messagePage = $requestedMessagePage > 0
            ? min(max(1, $requestedMessagePage), $messageTotalPages)
            : $messageTotalPages;
        $messages = $messageRepository->findForConnectionPaginated(
            $connection,
            $messagePage,
            $messagePerPage,
        );

        return $this->render('penpal/chat.html.twig', [
            'connection' => $connection,
            'friend' => $friend,
            'archive' => $archive,
            'messages' => $messages,
            'message_pagination' => [
                'currentPage' => $messagePage,
                'totalPages' => $messageTotalPages,
                'totalItems' => $messageTotalItems,
            ],
        ]);
    }

    private function checkDecision(BuddyConnection $connection, Request $request, string $action): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($connection->getReceiver() !== $this->getUser() || $connection->getStatus() !== 'pending') {
            throw $this->createAccessDeniedException();
        }
        if (!$this->isCsrfTokenValid('penpal_'.$action.'_'.$connection->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }
    
    private function inferSourceTask(User $firstUser, User $secondUser, \DateTimeImmutable $connectionDate): ?BridgeTask
    {
        $earliestTask = null;
        $earliestInteraction = null;

        foreach ([$firstUser, $secondUser] as $taskAuthor) {
            $answerAuthor = $taskAuthor === $firstUser ? $secondUser : $firstUser;

            foreach ($taskAuthor->getBridgeTasks() as $task) {
                foreach ($task->getResponses() as $response) {
                    if ($response->getAuthor() !== $answerAuthor || $response->getCreatedAt() > $connectionDate) {
                        continue;
                    }
                    if (!$earliestInteraction || $response->getCreatedAt() < $earliestInteraction) {
                        $earliestTask = $task;
                        $earliestInteraction = $response->getCreatedAt();
                    }
                }
            }
        }

        return $earliestTask;
    }
}
