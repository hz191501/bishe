<?php

namespace App\Tests\Controller;

use App\Controller\SharedNoteController;
use App\Entity\{User, Category, BridgeTask, TaskResponse, BuddyConnection, SharedNote, SharedNoteComment};
use App\Repository\{BuddyConnectionRepository, SharedNoteRepository};
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * 使用 SQLite 内存库真实保存与查询实体，不读取 .env，也不连接用户的 MariaDB。
 * 每个测试重新建库，既能检查外键和唯一约束，又不会留下演示用户或测试留言。
 */
final class SharedNoteWorkflowTest extends TestCase
{
    private EntityManager $em;
    private User $owner;
    private User $friend;
    private User $stranger;
    private BuddyConnection $connection;
    private TaskResponse $answer;
    private array $rendered;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2).'/src/Entity'], true);
        // 与项目 doctrine.yaml 一致，将属性名转换为数据库的下划线列名。
        $config->setNamingStrategy(new \Doctrine\ORM\Mapping\UnderscoreNamingStrategy(CASE_LOWER, true));
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('PRAGMA foreign_keys = ON');
        $this->em = new EntityManager($db, $config);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->owner = $this->user('owner');
        $this->friend = $this->user('friend');
        $this->stranger = $this->user('stranger');
        $category = (new Category())->setName('Lingua');
        $task = (new BridgeTask())->setTitle('Scrivere una mail')->setDescription('Come posso scrivere una mail?')->setAuthor($this->owner)->setCategory($category)->setStatus('open')->setCreatedAt(new \DateTimeImmutable());
        $this->answer = (new TaskResponse())->setAuthor($this->friend)->setTask($task)->setContent('Puoi iniziare con Gentile Segreteria.')->setIsBestAnswer(false)->setCreatedAt(new \DateTimeImmutable());
        $this->connection = (new BuddyConnection())->setRequester($this->owner)->setReceiver($this->friend)->setStatus('accepted')->setCreatedAt(new \DateTimeImmutable())->setAcceptedAt(new \DateTimeImmutable());
        foreach ([$category, $task, $this->answer, $this->connection] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
    }

    protected function tearDown(): void { $this->em->close(); $this->em->getConnection()->close(); }

    public function testSaveEditAndBothUsersAddDetails(): void
    {
        $note = $this->save();
        self::assertSame($this->answer, $note->getSourceResponse());
        self::assertSame($this->answer->getContent(), $note->getSourceExcerpt());
        self::assertSame($this->owner, $note->getAuthor());
        $controller = $this->controller($this->owner);
        $controller->edit($note, $this->request(['title' => 'Una formula utile', 'content' => 'La userò in una mail.', 'type' => 'word', 'source_excerpt' => 'forged', 'author_id' => $this->stranger->getId()]), $this->em);
        self::assertSame('Una formula utile', $note->getTitle());
        self::assertNotNull($note->getUpdatedAt());
        self::assertSame($this->answer->getContent(), $note->getSourceExcerpt());
        self::assertSame($this->owner, $note->getAuthor());
        foreach ([$this->owner, $this->friend] as $user) {
            $this->controller($user)->comment($note, $this->request(['content' => 'Un esempio diverso.', 'author_id' => $this->stranger->getId()]), $this->em);
        }
        $comments = $this->em->createQuery('SELECT c FROM App\Entity\SharedNoteComment c ORDER BY c.id')->getResult();
        self::assertCount(2, $comments);
        self::assertSame($this->owner, $comments[0]->getAuthor());
        self::assertSame($this->friend, $comments[1]->getAuthor());
        $this->controller($this->friend)->deleteComment($comments[1], $this->request(), $this->em);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note_comment'));
    }

    public function testDuplicateSaveOpensExistingNoteWithoutOverwriting(): void
    {
        $note = $this->save();
        $second = $this->save($this->friend);
        self::assertSame($note->getId(), $second->getId());
        self::assertSame($this->owner, $second->getAuthor());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note'));
    }

    public function testDatabaseAlsoRejectsConcurrentDuplicate(): void
    {
        $note = $this->save();
        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->em->getConnection()->executeStatement('INSERT INTO shared_note (connection_id, author_id, source_response_id, type, title, content, created_at) SELECT connection_id, author_id, source_response_id, type, title, content, created_at FROM shared_note WHERE id = ?', [$note->getId()]);
    }

    public function testDeletedSourceKeepsSnapshotAndDeletingNoteRemovesAdditions(): void
    {
        $note = $this->save();
        $id = $note->getId();
        $this->controller($this->friend)->comment($note, $this->request(['content' => 'Da ricordare.']), $this->em);
        $this->em->getConnection()->executeStatement('DELETE FROM task_response WHERE id = ?', [$this->answer->getId()]);
        $this->em->clear();
        $note = $this->em->find(SharedNote::class, $id);
        self::assertNull($note->getSourceResponse());
        self::assertSame('Puoi iniziare con Gentile Segreteria.', $note->getSourceExcerpt());
        self::assertCount(1, $note->getComments());
        $this->em->remove($note);
        $this->em->flush();
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note_comment'));
    }

    public function testStrangerAndRemovedFriendCannotAccessOrChangeNotes(): void
    {
        $note = $this->save();
        foreach ([$this->stranger, $this->friend] as $user) {
            if ($user === $this->friend) { $this->connection->setStatus('removed'); }
            foreach (['show', 'edit', 'comment', 'delete'] as $action) {
                try {
                    $controller = $this->controller($user);
                    $action === 'show' ? $controller->show($note) : $controller->$action($note, $this->request(['content' => 'forged']), $this->em);
                    self::fail('Private note action was allowed.');
                } catch (AccessDeniedException) { self::assertSame('Una formula da ricordare', $note->getTitle()); }
            }
        }
    }

    public function testFriendCannotEditOriginalOrDeleteAnotherUsersAddition(): void
    {
        $note = $this->save();
        $this->controller($this->owner)->comment($note, $this->request(['content' => 'Mia aggiunta.']), $this->em);
        $comment = $this->em->createQuery('SELECT c FROM App\Entity\SharedNoteComment c')->getSingleResult();
        foreach (['edit', 'deleteComment'] as $action) {
            try {
                $this->controller($this->friend)->$action($action === 'edit' ? $note : $comment, $this->request(), $this->em);
                self::fail('Other author content was editable.');
            } catch (AccessDeniedException) { self::assertNotNull($note->getId()); }
        }
    }

    public function testInvalidInputAndForgedFriendshipDoNotWrite(): void
    {
        [$connections, $notes] = $this->repositories();
        $controller = $this->controller($this->owner);
        foreach ([['connection_id' => '9999'], ['content' => '   '], ['title' => str_repeat('a', 121)], ['type' => 'unknown']] as $override) {
            $controller->fromResponse($this->answer, $this->request(array_merge($this->values(), $override)), $connections, $notes, $this->em);
            self::assertNotNull($this->rendered['error']);
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note'));
        }
    }

    public function testCsrfAndLoggedOutAccessAreRejected(): void
    {
        $note = $this->save();
        foreach (['edit', 'comment'] as $action) {
            try {
                $this->controller($this->owner)->$action($note, $this->request(['_token' => 'invalid']), $this->em);
                self::fail('Invalid token accepted.');
            } catch (AccessDeniedException) { self::assertNull($note->getUpdatedAt()); }
        }
        $this->expectException(AccessDeniedException::class);
        $this->controller(null)->show($note);
    }

    public function testGetAndLegacyNotesRemainCompatible(): void
    {
        $controller = $this->controller($this->owner);
        [$connections, $notes] = $this->repositories();
        $controller->fromResponse($this->answer, Request::create('/', 'GET'), $connections, $notes, $this->em);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note'));
        $controller->add($this->connection, $this->request($this->values()), $this->em);
        $note = $this->em->createQuery('SELECT n FROM App\Entity\SharedNote n')->getSingleResult();
        self::assertNull($note->getSourceResponse());
        self::assertNull($note->getSourceExcerpt());
        $controller->show($note);
        self::assertSame($note, $this->rendered['note']);
    }

    public function testAdditionCountsAsMutualContributionButNotAsAnExtraNote(): void
    {
        $note = $this->save();
        $ownerId = $this->owner->getId();
        $friendId = $this->friend->getId();
        $this->controller($this->friend)->comment($note, $this->request(['content' => 'Anche in questo caso funziona.']), $this->em);
        // 重新读取，模拟提交后新页面从数据库加载的双向关联集合。
        $this->em->clear();
        $journey = new \App\Service\BridgeJourneyService();
        $ownerBadges = array_column($journey->buildPassport($this->em->find(User::class, $ownerId))['badges'], null, 'key');
        $friendBadges = array_column($journey->buildPassport($this->em->find(User::class, $friendId))['badges'], null, 'key');
        self::assertTrue($ownerBadges['mutual_notes']['unlocked']);
        self::assertTrue($friendBadges['mutual_notes']['unlocked']);
        self::assertTrue($ownerBadges['first_note']['unlocked']);
        self::assertFalse($friendBadges['first_note']['unlocked']);
        self::assertFalse($ownerBadges['three_cultures']['unlocked']);
    }

    public function testInvalidAdditionsKeepInputAndDoNotCreateRows(): void
    {
        $note = $this->save();
        foreach (['  ', str_repeat('中', 1001)] as $content) {
            $this->controller($this->friend)->comment($note, $this->request(['content' => $content]), $this->em);
            self::assertNotNull($this->rendered['error']);
            self::assertSame(trim($content), $this->rendered['comment_content']);
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM shared_note_comment'));
        }
    }

    private function user(string $name): User
    {
        $user = (new User())->setUsername($name)->setEmail($name.'@example.test')->setPassword('unused-test-hash')->setRoles([])->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($user);
        return $user;
    }

    private function values(): array { return ['connection_id' => (string) $this->connection->getId(), 'type' => 'word', 'title' => 'Una formula da ricordare', 'content' => 'La userò per scrivere alla segreteria.']; }
    private function request(array $values = []): Request { return Request::create('/', 'POST', array_merge(['_token' => 'valid'], $values)); }

    private function repositories(): array
    {
        $connections = $this->createMock(BuddyConnectionRepository::class);
        $connections->method('findAcceptedFor')->willReturnCallback(fn (User $user) => in_array($user, [$this->owner, $this->friend], true) && $this->connection->getStatus() === 'accepted' ? [$this->connection] : []);
        $notes = $this->createMock(SharedNoteRepository::class);
        $notes->method('findOneBy')->willReturnCallback(fn (array $criteria) => $this->em->createQuery('SELECT n FROM App\Entity\SharedNote n WHERE n.connection = :connection AND n.sourceResponse = :sourceResponse')->setParameters($criteria)->getOneOrNullResult());
        return [$connections, $notes];
    }

    private function save(?User $user = null): SharedNote
    {
        [$connections, $notes] = $this->repositories();
        $this->controller($user ?? $this->owner)->fromResponse($this->answer, $this->request($this->values()), $connections, $notes, $this->em);
        return $this->em->createQuery('SELECT n FROM App\Entity\SharedNote n')->getSingleResult();
    }

    private function controller(?User $user): SharedNoteController
    {
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack(); $stack->push($request);
        $storage = new TokenStorage();
        if ($user) { $storage->setToken(new UsernamePasswordToken($user, 'main', ['ROLE_USER'])); }
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($user !== null);
        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(static fn ($token) => $token->getValue() === 'valid');
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturn('/note');
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(function ($name, $values) { $this->rendered = $values; return 'page'; });
        $container = new Container();
        foreach (['request_stack' => $stack, 'security.token_storage' => $storage, 'security.authorization_checker' => $auth, 'security.csrf.token_manager' => $csrf, 'router' => $router, 'twig' => $twig] as $key => $service) { $container->set($key, $service); }
        $controller = new SharedNoteController(); $controller->setContainer($container);
        return $controller;
    }
}
