<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** 补充留言保留各自的作者和时间，让笔友补充使用场景而不改写对方的原笔记。 */
#[ORM\Entity]
#[ORM\Index(name: 'IDX_NOTE_COMMENT_NOTE', columns: ['note_id'])]
#[ORM\Index(name: 'IDX_NOTE_COMMENT_AUTHOR', columns: ['author_id'])]
class SharedNoteComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 删除笔记时补充一起删除；解除好友只更新关系状态，不会删除笔记。
    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SharedNote $note = null;

    // 作者用于页面署名和删除权限，始终由控制器取当前登录用户。
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    // 控制器限制为 1～1000 字符；Twig 默认转义后显示，避免文字被当作代码执行。
    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    // 用于按先后顺序展示补充，也参与双方共同贡献徽章的解锁时间计算。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getNote(): ?SharedNote
    {
        return $this->note;
    }
    public function setNote(SharedNote $note): static
    {
        $this->note = $note;
        return $this;
    }
    public function getAuthor(): ?User
    {
        return $this->author;
    }
    public function setAuthor(User $author): static
    {
        $this->author = $author;
        return $this;
    }
    public function getContent(): ?string
    {
        return $this->content;
    }
    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function setCreatedAt(\DateTimeImmutable $date): static
    {
        $this->createdAt = $date;
        return $this;
    }
}
