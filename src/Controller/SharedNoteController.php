<?php

/*
 * 共享笔记控制器：让已经成为好友的用户保存或删除共同笔记，
 * 并确保只有相关好友能够访问这些内容。
 */

namespace App\Controller;

use App\Entity\BuddyConnection;
use App\Entity\SharedNote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/amici-di-penna')]
class SharedNoteController extends AbstractController
{
    private const TYPES = ['word', 'correction', 'culture', 'place', 'food', 'advice'];

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
