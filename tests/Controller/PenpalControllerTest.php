<?php

namespace App\Tests\Controller;

use App\Controller\PenpalController;
use App\Entity\BuddyConnection;
use App\Entity\User;
use App\Repository\BridgeTaskRepository;
use App\Repository\BuddyConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PenpalControllerTest extends TestCase
{
    public function testRejectedRequestCanBeSentAgainInEitherDirection(): void
    {
        foreach ([false, true] as $reverse) {
            $user = new User();
            $friend = new User();
            $connection = (new BuddyConnection())->setStatus('rejected')
                ->setRequester($reverse ? $friend : $user)->setReceiver($reverse ? $user : $friend)
                ->setCreatedAt(new \DateTimeImmutable('2026-07-01'));
            [$controller, $request] = $this->controller($user);
            $repository = $this->createMock(BuddyConnectionRepository::class);
            $repository->method('findBetween')->willReturn($connection);
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::once())->method('persist')->with(self::identicalTo($connection));
            $manager->expects(self::once())->method('flush');
            $response = $controller->requestFriend($friend, $request, $repository, $this->createMock(BridgeTaskRepository::class), $manager);
            self::assertSame(302, $response->getStatusCode());
            self::assertSame('pending', $connection->getStatus());
            self::assertSame($user, $connection->getRequester());
            self::assertSame($friend, $connection->getReceiver());
            self::assertNull($connection->getAcceptedAt());
            self::assertGreaterThan(new \DateTimeImmutable('2026-07-01'), $connection->getCreatedAt());
        }
    }

    public function testPendingAndAcceptedRelationshipsPreventDuplicateRequests(): void
    {
        foreach (['pending', 'accepted'] as $status) {
            $user = new User();
            $friend = new User();
            $connection = (new BuddyConnection())->setRequester($user)->setReceiver($friend)->setStatus($status);
            [$controller, $request] = $this->controller($user);
            $repository = $this->createMock(BuddyConnectionRepository::class);
            $repository->method('findBetween')->willReturn($connection);
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::never())->method('persist');
            $manager->expects(self::never())->method('flush');
            $controller->requestFriend($friend, $request, $repository, $this->createMock(BridgeTaskRepository::class), $manager);
            self::assertSame($status, $connection->getStatus());
        }
    }

    public function testInvalidCsrfTokenCannotSendRequest(): void
    {
        [$controller, $request] = $this->controller(new User(), false);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $controller->requestFriend(new User(), $request, $this->createMock(BuddyConnectionRepository::class), $this->createMock(BridgeTaskRepository::class), $manager);
    }

    public function testThirdPartyCannotAcceptRequest(): void
    {
        [$controller, $request] = $this->controller(new User());
        $connection = (new BuddyConnection())->setRequester(new User())->setReceiver(new User())->setStatus('pending');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $controller->accept($connection, $request, $manager);
    }

    public function testEitherFriendCanRemoveWithoutDeletingHistory(): void
    {
        foreach ([false, true] as $receiverRemoves) {
            $user = new User();
            $friend = new User();
            $connection = (new BuddyConnection())->setRequester($user)->setReceiver($friend)->setStatus('accepted')
                ->setAcceptedAt(new \DateTimeImmutable());
            $letter = (new \App\Entity\PenpalMessage())->setAuthor($user);
            $note = (new \App\Entity\SharedNote())->setAuthor($friend);
            $connection->addMessage($letter)->addSharedNote($note);
            [$controller, $request] = $this->controller($receiverRemoves ? $friend : $user);
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::once())->method('flush');
            $manager->expects(self::never())->method('remove');
            self::assertSame(302, $controller->removeFriend($connection, $request, $manager)->getStatusCode());
            self::assertSame('removed', $connection->getStatus());
            self::assertNull($connection->getAcceptedAt());
            self::assertTrue($connection->getMessages()->contains($letter));
            self::assertTrue($connection->getSharedNotes()->contains($note));
        }
    }

    public function testThirdPartyCannotRemoveFriendship(): void
    {
        [$controller, $request] = $this->controller(new User());
        $connection = (new BuddyConnection())->setRequester(new User())->setReceiver(new User())->setStatus('accepted');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $controller->removeFriend($connection, $request, $manager);
    }

    public function testInvalidTokenCannotRemoveFriendship(): void
    {
        $user = new User();
        [$controller, $request] = $this->controller($user, false);
        $connection = (new BuddyConnection())->setRequester($user)->setReceiver(new User())->setStatus('accepted');
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');
        $this->expectException(AccessDeniedException::class);
        $controller->removeFriend($connection, $request, $manager);
    }

    public function testRepeatedRemovalDoesNotChangeInactiveRelationship(): void
    {
        foreach (['removed', 'pending', 'rejected'] as $status) {
            $user = new User();
            [$controller, $request] = $this->controller($user);
            $connection = (new BuddyConnection())->setRequester($user)->setReceiver(new User())->setStatus($status);
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::never())->method('flush');
            $controller->removeFriend($connection, $request, $manager);
            self::assertSame($status, $connection->getStatus());
        }
    }

    public function testRemovedFriendsMustApplyAndAcceptAgainToRestoreHistory(): void
    {
        $user = new User();
        $friend = new User();
        $origin = new \App\Entity\BridgeTask();
        $connection = (new BuddyConnection())->setRequester($friend)->setReceiver($user)->setStatus('removed')->setSourceTask($origin);
        $letter = (new \App\Entity\PenpalMessage())->setAuthor($friend);
        $connection->addMessage($letter);
        [$controller, $request] = $this->controller($user);
        $repository = $this->createMock(BuddyConnectionRepository::class);
        $repository->method('findBetween')->willReturn($connection);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::once())->method('persist')->with(self::identicalTo($connection));
        $manager->expects(self::exactly(2))->method('flush');
        $controller->requestFriend($friend, $request, $repository, $this->createMock(BridgeTaskRepository::class), $manager);
        self::assertSame('pending', $connection->getStatus());
        self::assertSame($origin, $connection->getSourceTask());
        [$receiverController, $acceptRequest] = $this->controller($friend);
        $receiverController->accept($connection, $acceptRequest, $manager);
        self::assertSame('accepted', $connection->getStatus());
        self::assertNotNull($connection->getAcceptedAt());
        self::assertTrue($connection->getMessages()->contains($letter));
    }

    public function testRemovedFriendCannotReadOrSendLetters(): void
    {
        foreach (['GET', 'POST'] as $method) {
            $user = new User();
            [$controller, $request] = $this->controller($user);
            $request->setMethod($method);
            $connection = (new BuddyConnection())->setRequester($user)->setReceiver(new User())->setStatus('removed');
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::never())->method('flush');
            try {
                $controller->chat($connection, $request, $manager, new \App\Service\BridgeJourneyService(), $this->createMock(\App\Repository\PenpalMessageRepository::class));
                self::fail('Removed friendship must not allow letters.');
            } catch (AccessDeniedException) {
                self::assertSame('removed', $connection->getStatus());
            }
        }
    }

    public function testConfirmationPageDoesNotRemoveFriend(): void
    {
        $user = new User();
        [$controller, $request, $container] = $this->controller($user);
        $request->setMethod('GET');
        $connection = (new BuddyConnection())->setRequester($user)->setReceiver(new User())->setStatus('accepted');
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->expects(self::once())->method('render')->with('penpal/remove.html.twig', self::arrayHasKey('friend'))->willReturn('Confirmation');
        $container->set('twig', $twig);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects(self::never())->method('flush');
        self::assertSame(200, $controller->removeFriend($connection, $request, $manager)->getStatusCode());
        self::assertSame('accepted', $connection->getStatus());
    }

    public function testRemovedFriendsCannotAddOrDeleteSharedNotes(): void
    {
        foreach (['add', 'delete'] as $action) {
            $user = new User();
            [, $request, $container] = $this->controller($user);
            $controller = new \App\Controller\SharedNoteController();
            $controller->setContainer($container);
            $connection = (new BuddyConnection())->setRequester($user)->setReceiver(new User())->setStatus('removed');
            $note = (new \App\Entity\SharedNote())->setAuthor($user)->setConnection($connection);
            $manager = $this->createMock(EntityManagerInterface::class);
            $manager->expects(self::never())->method('flush');
            try {
                $controller->$action($action === 'add' ? $connection : $note, $request, $manager);
                self::fail('Removed friendship must not allow shared notes.');
            } catch (AccessDeniedException) {
                self::assertSame('removed', $connection->getStatus());
            }
        }
    }

    private function controller(User $user, bool $validCsrf = true): array
    {
        $request = Request::create('/amici-di-penna/richiesta/1', 'POST', ['_token' => 'test', 'return_to' => 'penpal']);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER']));
        $authorization = $this->createMock(AuthorizationCheckerInterface::class);
        $authorization->method('isGranted')->willReturn(true);
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($validCsrf);
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/amici-di-penna');
        $container = new Container();
        $container->set('security.token_storage', $tokens);
        $container->set('security.authorization_checker', $authorization);
        $container->set('security.csrf.token_manager', $csrf);
        $container->set('request_stack', $stack);
        $container->set('router', $router);
        $controller = new PenpalController();
        $controller->setContainer($container);
        return [$controller, $request, $container];
    }
}
