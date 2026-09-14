<?php

/*
 * 共享笔记控制器：处理手动笔记、从公开回答保存、作者编辑和双方补充。
 * 每个入口先检查好友状态和参与者身份；写操作再验证 CSRF。
 * 公开回答仍公开，保存后的学习记录与补充只属于选中的两位好友。
 */

namespace App\Controller;

use App\Entity\BuddyConnection;
use App\Entity\SharedNote;
use App\Entity\SharedNoteComment;
use App\Entity\TaskResponse;
use App\Repository\BuddyConnectionRepository;
use App\Repository\SharedNoteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/amici-di-penna')]
class SharedNoteController extends AbstractController
{
    private const TYPES = ['word', 'correction', 'culture', 'place', 'food', 'advice'];

    /**
     * 将公开回答保存到自己的某一本共同笔记。
     * GET 只预览；POST 必须确认好友、填写学习记录并通过 CSRF 校验后才保存。
     */
    #[Route('/quaderno/risposta/{id}', name: 'app_shared_note_from_response', methods: ['GET', 'POST'])]
    public function fromResponse(TaskResponse $response, Request $request, BuddyConnectionRepository $connections, SharedNoteRepository $notes, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        // 选择范围来自服务器，只包含当前用户已接受的好友，不能填任意关系编号。
        $available = $connections->findAcceptedFor($user);
        $values = ['type' => 'advice', 'title' => mb_substr($response->getTask()->getTitle(), 0, 120), 'content' => '', 'connection_id' => ''];
        $error = null;
        if ($request->isMethod('POST')) {
            $this->checkToken($request, 'save_response_'.$response->getId());
            $values = $this->readNoteValues($request);
            $values['connection_id'] = $request->request->getString('connection_id');
            $connection = null;
            foreach ($available as $candidate) {
                if ((string) $candidate->getId() === $values['connection_id']) {
                    $connection = $candidate;
                    break;
                }
            }
            if (!$connection) {
                $error = 'Scegli un amico con cui hai un’amicizia attiva.';
            } else {
                // 再次检查状态，避免过期页面或伪造请求绕过私密内容权限。
                $this->checkParticipant($connection);
                $existing = $notes->findOneBy(['connection' => $connection, 'sourceResponse' => $response]);
                if ($existing) {
                    $this->addFlash('info', 'Questa risposta è già nel vostro quaderno. Puoi aggiungere un dettaglio alla nota esistente.');
                    return $this->redirectToRoute('app_shared_note_show', ['id' => $existing->getId()]);
                }
                $error = $this->validateNoteValues($values);
                if ($error === null) {
                    $note = (new SharedNote())->setConnection($connection)->setAuthor($user)
                        ->setType($values['type'])->setTitle($values['title'])->setContent($values['content'])
                        ->setCreatedAt(new \DateTimeImmutable())
                        ->setSourceResponse($response)->setSourceExcerpt($response->getContent())
                        ->setSourceTitle($response->getTask()->getTitle());
                    $entityManager->persist($note);
                    try {
                        // 数据库唯一索引处理双击或两人同时保存的情况，保证同一本中只有一份。
                        $entityManager->flush();
                    } catch (UniqueConstraintViolationException) {
                        $this->addFlash('info', 'La risposta è già stata salvata nel quaderno.');
                        return $this->redirectToRoute('app_penpal_chat', ['id' => $connection->getId(), '_fragment' => 'quaderno-del-ponte']);
                    }
                    $this->addFlash('success', 'Risposta salvata nel quaderno condiviso.');
                    return $this->redirectToRoute('app_shared_note_show', ['id' => $note->getId()]);
                }
            }
        }
        // 校验失败时保留已输入内容，用户不必重新写学习记录。
        return $this->render('shared_note/from_response.html.twig', ['source_response' => $response, 'connections' => $available, 'values' => $values, 'error' => $error]);
    }

    #[Route('/quaderno/{id}', name: 'app_shared_note_show', methods: ['GET'])]
    public function show(SharedNote $note): Response
    {
        $this->checkParticipant($note->getConnection());
        return $this->render('shared_note/show.html.twig', ['note' => $note, 'comment_content' => '', 'error' => null]);
    }

    #[Route('/quaderno/{id}/modifica', name: 'app_shared_note_edit', methods: ['GET', 'POST'])]
    public function edit(SharedNote $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkParticipant($note->getConnection());
        // 编辑权仅属于原作者；管理员身份也不能改写别人的私密学习记录。
        if ($note->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        $values = ['type' => $note->getType(), 'title' => $note->getTitle(), 'content' => $note->getContent()];
        $error = null;
        if ($request->isMethod('POST')) {
            $this->checkToken($request, 'edit_note_'.$note->getId());
            $values = $this->readNoteValues($request);
            $error = $this->validateNoteValues($values);
            if ($error === null) {
                // 只修改表单允许的三个字段，来源、作者、所属好友和摘录都不能被替换。
                $note->setType($values['type'])->setTitle($values['title'])->setContent($values['content'])->setUpdatedAt(new \DateTimeImmutable());
                $entityManager->flush();
                $this->addFlash('success', 'Nota aggiornata.');
                return $this->redirectToRoute('app_shared_note_show', ['id' => $note->getId()]);
            }
        }
        return $this->render('shared_note/edit.html.twig', ['note' => $note, 'values' => $values, 'error' => $error]);
    }

    #[Route('/quaderno/{id}/aggiunta', name: 'app_shared_note_comment', methods: ['POST'])]
    public function comment(SharedNote $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkParticipant($note->getConnection());
        $this->checkToken($request, 'note_comment_'.$note->getId());
        $content = trim($request->request->getString('content'));
        if ($content === '' || mb_strlen($content) > 1000) {
            return $this->render('shared_note/show.html.twig', ['note' => $note, 'comment_content' => $content, 'error' => 'Scrivi un’aggiunta da 1 a 1000 caratteri.']);
        }
        // 双方可以补充；作者永远取登录用户，不能由隐藏字段指定。
        $comment = (new SharedNoteComment())->setNote($note)->setAuthor($this->getUser())->setContent($content)->setCreatedAt(new \DateTimeImmutable());
        $entityManager->persist($comment);
        $entityManager->flush();
        return $this->redirectToRoute('app_shared_note_show', ['id' => $note->getId(), '_fragment' => 'aggiunte']);
    }

    #[Route('/quaderno/aggiunta/{id}/elimina', name: 'app_shared_note_comment_delete', methods: ['POST'])]
    public function deleteComment(SharedNoteComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $note = $comment->getNote();
        $this->checkParticipant($note->getConnection());
        $this->checkToken($request, 'delete_note_comment_'.$comment->getId());
        if ($comment->getAuthor() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }
        $entityManager->remove($comment);
        $entityManager->flush();
        return $this->redirectToRoute('app_shared_note_show', ['id' => $note->getId(), '_fragment' => 'aggiunte']);
    }

    /** 与原有手动笔记保持相同字段和长度限制，避免两套不一致的输入规则。 */
    private function readNoteValues(Request $request): array
    {
        return ['type' => trim($request->request->getString('type')), 'title' => trim($request->request->getString('title')), 'content' => trim($request->request->getString('content'))];
    }

    private function validateNoteValues(array $values): ?string
    {
        if (!in_array($values['type'], self::TYPES, true)) { return 'Seleziona un tipo di nota valido.'; }
        if ($values['title'] === '' || $values['content'] === '') { return 'Inserisci un titolo e ciò che vuoi ricordare.'; }
        if (mb_strlen($values['title']) > 120 || mb_strlen($values['content']) > 2000) { return 'Il titolo può contenere 120 caratteri e la nota 2000.'; }
        return null;
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Token di sicurezza non valido.');
        }
    }

    #[Route('/{id}/quaderno', name: 'app_shared_note_add', methods: ['POST'])]
    public function add(BuddyConnection $connection, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->checkParticipant($connection);
        if (!$this->isCsrfTokenValid('shared_note_'.$connection->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $type = trim((string) $request->request->get('type'));
        $title = trim((string) $request->request->get('title'));
        $content = trim((string) $request->request->get('content'));

        if (!in_array($type, self::TYPES, true)) {
            $this->addFlash('warning', 'Seleziona un tipo di nota valido.');
        } elseif ($title === '' || $content === '') {
            $this->addFlash('warning', 'Inserisci un titolo e un contenuto.');
        } elseif (mb_strlen($title) > 120 || mb_strlen($content) > 2000) {
            $this->addFlash('warning', 'La nota supera la lunghezza consentita.');
        } else {
            $note = (new SharedNote())
                ->setConnection($connection)
                ->setAuthor($this->getUser())
                ->setType($type)
                ->setTitle($title)
                ->setContent($content)
                ->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($note);
            $entityManager->flush();
            $this->addFlash('success', 'Nota aggiunta al quaderno condiviso.');
        }

        return $this->redirectToRoute('app_penpal_chat', ['id' => $connection->getId(), '_fragment' => 'quaderno-del-ponte']);
    }

    #[Route('/quaderno/{id}/elimina', name: 'app_shared_note_delete', methods: ['POST'])]
    public function delete(SharedNote $note, Request $request, EntityManagerInterface $entityManager): Response
    {
        $connection = $note->getConnection();
        $this->checkParticipant($connection);
        if (!$this->isCsrfTokenValid('delete_shared_note_'.$note->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if ($note->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($note);
        $entityManager->flush();
        $this->addFlash('success', 'Nota eliminata dal quaderno.');

        return $this->redirectToRoute('app_penpal_chat', ['id' => $connection->getId(), '_fragment' => 'quaderno-del-ponte']);
    }

    
    private function checkParticipant(BuddyConnection $connection): void
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        if ($connection->getStatus() !== 'accepted' || ($connection->getRequester() !== $user && $connection->getReceiver() !== $user)) {
            throw $this->createAccessDeniedException();
        }
    }
}
