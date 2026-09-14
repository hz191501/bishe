<?php

namespace App\Tests\Controller;

use App\Entity\{User, Category, BridgeTask, TaskResponse, BuddyConnection};
use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/** 通过真实 Symfony 路由、登录会话、CSRF 和 Twig 提交表单；数据库仍是独立内存库。 */
final class SharedNoteHttpTest extends TestCase
{
    public function testSaveEditAndSupplementThroughRealPages(): void
    {
        $previous = [];
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'APP_SECRET' => 'isolated-test-only', 'APP_ENV' => 'test'] as $key => $value) {
            $previous[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = $value; putenv($key.'='.$value);
        }
        $kernel = new Kernel('test', false);
        $client = new KernelBrowser($kernel);
        $client->disableReboot();
        try {
            $kernel->boot();
            $em = $client->getContainer()->get('doctrine')->getManager();
            (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
            $first = (new User())->setEmail('one@example.test')->setUsername('Xiao')->setPassword('unused')->setRoles([])->setCreatedAt(new \DateTimeImmutable());
            $second = (new User())->setEmail('two@example.test')->setUsername('Giulia')->setPassword('unused')->setRoles([])->setCreatedAt(new \DateTimeImmutable());
            $category = (new Category())->setName('Lingua');
            $task = (new BridgeTask())->setAuthor($first)->setCategory($category)->setTitle('Una mail alla segreteria')->setDescription('Come posso iniziare?')->setStatus('open')->setCreatedAt(new \DateTimeImmutable());
            $answer = (new TaskResponse())->setAuthor($second)->setTask($task)->setContent('Puoi scrivere Gentile Segreteria.')->setIsBestAnswer(false)->setCreatedAt(new \DateTimeImmutable());
            $connection = (new BuddyConnection())->setRequester($first)->setReceiver($second)->setStatus('accepted')->setCreatedAt(new \DateTimeImmutable())->setAcceptedAt(new \DateTimeImmutable());
            foreach ([$first,$second,$category,$task,$answer,$connection] as $entity) { $em->persist($entity); }
            $em->flush();
            $firstId = $first->getId(); $secondId = $second->getId();
            $client->loginUser($first);
            $client->request('GET', '/amici-di-penna/quaderno/risposta/'.$answer->getId());
            self::assertSame(200, $client->getResponse()->getStatusCode());
            $client->submitForm('Salva nel quaderno', ['connection_id'=>(string)$connection->getId(), 'type'=>'word', 'title'=>'Una formula utile', 'content'=>'La userò nella prossima mail.']);
            self::assertSame(302, $client->getResponse()->getStatusCode());
            $noteUrl = $client->getResponse()->headers->get('Location');
            $crawler = $client->followRedirect();
            self::assertStringContainsString('Una formula utile', $crawler->filter('h1')->text());
            $client->clickLink('Modifica la mia nota');
            $client->submitForm('Salva modifiche', ['content'=>'Una formula utile in un contesto formale.']);
            self::assertSame(302, $client->getResponse()->getStatusCode());
            $client->followRedirect();
            // 换成第二位用户，确认不能编辑原笔记，但可以留下自己的补充。
            $client->loginUser($em->find(User::class, $secondId));
            $crawler = $client->request('GET', $noteUrl);
            self::assertSame(0, $crawler->selectLink('Modifica la mia nota')->count());
            $client->submitForm('Aggiungi al quaderno', ['content'=>'Puoi anche indicare il nome del destinatario.']);
            self::assertSame(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();
            self::assertStringContainsString('Puoi anche indicare', $crawler->filter('#aggiunte')->text());
            self::assertStringContainsString('Giulia', $crawler->filter('.note-addition strong')->text());
            // 再次打开共同笔记列表，验证旧聊天页中的新入口也能正常渲染。
            $client->loginUser($em->find(User::class, $firstId));
            $client->request('GET', '/amici-di-penna/'.$connection->getId().'/messaggi');
            self::assertSame(200, $client->getResponse()->getStatusCode());
        } finally {
            $kernel->shutdown();
            foreach ($previous as $key => [$env, $server, $process]) {
                if ($env === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $env; }
                if ($server === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $server; }
                $process === false ? putenv($key) : putenv($key.'='.$process);
            }
        }
    }
}
