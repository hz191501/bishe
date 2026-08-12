<?php

/* 通知实体：记录通知接收人、触发人、内容、相关链接和已读状态。 */

namespace App\Entity;

use App\Repository\CommunityNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommunityNotificationRepository::class)]

class CommunityNotification
{
    // 通知记录的自动主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // recipient 是通知接收者；删除用户时由数据库 CASCADE 删除相关通知。
    #[ORM\ManyToOne(inversedBy: 'communityNotifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $recipient = null;

    // actor 是触发点赞、回答或好友申请等动作的用户。
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $actor = null;

    // type 是程序判断通知种类的短标识，message 是给用户看的文字。
    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    // 点击通知时跳转到 targetPath 指向的帖子、个人资料或好友页面。
    #[ORM\Column(length: 255)]
    private ?string $targetPath = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // 未读通知的 readAt 为 NULL；打开通知列表后写入阅读时间。
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;

        return $this;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function setActor(?User $actor): static
    {
        $this->actor = $actor;

        return $this;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getTargetPath(): ?string
    {
        return $this->targetPath;
    }

    public function setTargetPath(string $targetPath): static
    {
        $this->targetPath = $targetPath;

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

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
