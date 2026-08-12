<?php

/* 好友关系实体：保存申请人、接收人、申请状态和成为好友的时间。 */

namespace App\Entity;

use App\Repository\BuddyConnectionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuddyConnectionRepository::class)]

class BuddyConnection
{
    // 自增主键，用来唯一识别一条好友关系。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // status 的常用值为 pending、accepted、rejected，表示申请处理阶段。
    #[ORM\Column(length: 20)]
    private ?string $status = null;

    // createdAt 是申请发出时间；acceptedAt 只有接受后才有值。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    // requester 是发起申请的人，对应 User 中 sentBuddyConnections 集合。
    #[ORM\ManyToOne(inversedBy: 'sentBuddyConnections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $requester = null;

    // receiver 是收到申请的人，对应 User 中 receivedBuddyConnections 集合。
    #[ORM\ManyToOne(inversedBy: 'receivedBuddyConnections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $receiver = null;

    // 如果两人通过某个帖子认识，这里可记录来源帖子；没有来源时允许为 NULL。
    #[ORM\ManyToOne]
    private ?BridgeTask $sourceTask = null;

    /**
     * @var Collection<int, PenpalMessage>
     */
    #[ORM\OneToMany(targetEntity: PenpalMessage::class, mappedBy: 'connection', orphanRemoval: true)]
    // 删除好友关系时 orphanRemoval 会一并删除失去归属的信件。
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    /**
     * @var Collection<int, SharedNote>
     */
    #[ORM\OneToMany(targetEntity: SharedNote::class, mappedBy: 'connection', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $sharedNotes;

    public function __construct()
    {
        // 一对多属性使用 Collection，因此构造时创建空集合。
        $this->messages = new ArrayCollection();
        $this->sharedNotes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getSourceTask(): ?BridgeTask
    {
        return $this->sourceTask;
    }

    public function setSourceTask(?BridgeTask $sourceTask): static
    {
        $this->sourceTask = $sourceTask;

        return $this;
    }

    /**
     * @return Collection<int, PenpalMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function getUnreadMessageCountFor(User $user): int
    {
        return $this->messages->filter(
            static fn (PenpalMessage $message): bool => $message->getAuthor() !== $user && !$message->isRead()
        )->count();
    }

    public function addMessage(PenpalMessage $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConnection($this);
        }

        return $this;
    }

    public function removeMessage(PenpalMessage $message): static
    {
        if ($this->messages->removeElement($message)) {
            // set the owning side to null (unless already changed)
            if ($message->getConnection() === $this) {
                $message->setConnection(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, SharedNote>
     */
    public function getSharedNotes(): Collection
    {
        return $this->sharedNotes;
    }

    public function addSharedNote(SharedNote $sharedNote): static
    {
        if (!$this->sharedNotes->contains($sharedNote)) {
            $this->sharedNotes->add($sharedNote);
            $sharedNote->setConnection($this);
        }

        return $this;
    }

    public function removeSharedNote(SharedNote $sharedNote): static
    {
        if ($this->sharedNotes->removeElement($sharedNote) && $sharedNote->getConnection() === $this) {
            $sharedNote->setConnection(null);
        }

        return $this;
    }
}
