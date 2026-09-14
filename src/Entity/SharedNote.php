<?php

/* 共享笔记实体：保存好友共同记录的内容、作者和创建时间。 */

namespace App\Entity;

use App\Repository\SharedNoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: SharedNoteRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_note_connection_response', columns: ['connection_id', 'source_response_id'])]
#[ORM\Index(name: 'IDX_NOTE_SOURCE', columns: ['source_response_id'])]
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

    // 来源回答可为空，兼容旧笔记；原回答删除后仅断开关联，不删除笔记。
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?TaskResponse $sourceResponse = null;

    // 保存当时的原回答及帖子标题；由服务器填写，不能通过表单伪造。
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sourceExcerpt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sourceTitle = null;

    // 作者修改个人笔记时更新时间，原回答摘录和创建时间保持不变。
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, SharedNoteComment> */
    #[ORM\OneToMany(targetEntity: SharedNoteComment::class, mappedBy: 'note', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $comments;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
    }
    public function getSourceResponse(): ?TaskResponse
    {
        return $this->sourceResponse;
    }
    public function setSourceResponse(?TaskResponse $response): static
    {
        $this->sourceResponse = $response;
        return $this;
    }
    public function getSourceExcerpt(): ?string
    {
        return $this->sourceExcerpt;
    }
    public function setSourceExcerpt(?string $excerpt): static
    {
        $this->sourceExcerpt = $excerpt;
        return $this;
    }
    public function getSourceTitle(): ?string
    {
        return $this->sourceTitle;
    }
    public function setSourceTitle(?string $title): static
    {
        $this->sourceTitle = $title;
        return $this;
    }
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
    public function setUpdatedAt(?\DateTimeImmutable $date): static
    {
        $this->updatedAt = $date;
        return $this;
    }

    /** @return Collection<int, SharedNoteComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

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
