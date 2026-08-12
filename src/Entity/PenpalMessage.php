<?php

/* 好友信件实体：保存好友之间发送的文字、发送时间和阅读状态。 */

namespace App\Entity;

use App\Repository\PenpalMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PenpalMessageRepository::class)]

class PenpalMessage
{
    // 每封站内信的自动主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 主题是可选的，所以 nullable=true；正文使用 TEXT 保存较长内容。
    #[ORM\Column(length: 150, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    // 创建时间用于排列信件顺序。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // 数据库默认未读；接收方打开好友通信页面后会更新为 true。
    #[ORM\Column(options: ['default' => false])]
    private bool $isRead = false;

    // 多封信可以由同一用户发送，但每封信必须有作者。
    #[ORM\ManyToOne(inversedBy: 'penpalMessages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    // 每封信只属于一段好友关系，用它限制只有这两位好友可以查看。
    #[ORM\ManyToOne(inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BuddyConnection $connection = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): static
    {
        $this->subject = $subject;

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

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

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

    public function getConnection(): ?BuddyConnection
    {
        return $this->connection;
    }

    public function setConnection(?BuddyConnection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }
}
