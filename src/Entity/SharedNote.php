<?php

/* 共享笔记实体：保存好友共同记录的内容、作者和创建时间。 */

namespace App\Entity;

use App\Repository\SharedNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: SharedNoteRepository::class)]
class SharedNote
{
    // 共享笔记记录的自动主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // type 用于区分不同笔记类别，title 是卡片标题，content 是正文。
    #[ORM\Column(length: 30)]
    private ?string $type = null;

    #[ORM\Column(length: 120)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    // 保存笔记创建时间，页面可据此按新到旧排列。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // 笔记属于一段好友关系；关系删除时数据库级联删除笔记。
    #[ORM\ManyToOne(inversedBy: 'sharedNotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BuddyConnection $connection = null;

    // author 记录是谁创建了笔记，并用于限制只有作者能删除。
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getType(): ?string
    {
        return $this->type;
    }
    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(string $title): static
    {
        $this->title = $title;

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
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
    public function getConnection(): ?BuddyConnection
    {
        return $this->connection;
    }
    public function setConnection(?BuddyConnection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }
    public function getAuthor(): ?User
    {
        return $this->author;
    }
    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }
}
