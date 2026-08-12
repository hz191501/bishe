<?php

/*
 * 用户实体：保存账号、密码、角色和个人资料，
 * 同时维护用户与帖子、回答、评论、好友及点赞之间的关联。
 */

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Esiste già un account con questo indirizzo email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // 用户主键由数据库自动生成。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 邮箱既是登录标识，也通过实体上方 UniqueEntity 和数据库唯一约束防止重复。
    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Inserisci un indirizzo email.')]
    #[Assert\Email(message: 'Inserisci un indirizzo email valido.')]
    private ?string $email = null;

    /**
     * @var list<string> 权限角色列表，例如 ROLE_USER、ROLE_ADMIN。
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string 这里只保存哈希后的密码，绝不能保存用户输入的明文。
     */
    #[ORM\Column]
    private ?string $password = null;

    // 公开昵称显示在帖子、回答、好友和个人主页中。
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Inserisci un nome utente.')]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $username = null;

    // 以下个人资料均可选；nullable=true 表示注册时可以不填写。
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $nationality = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(choices: ['female', 'male', 'other', 'prefer_not_to_say'])]
    private ?string $gender = null;

    // 保存出生日期而不是固定年龄，这样年龄可以随日期自动更新。
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual(value: '-16 years', message: 'Devi avere almeno 16 anni.')]
    #[Assert\GreaterThanOrEqual(value: '-100 years', message: 'Controlla la data di nascita inserita.')]
    private ?\DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $spokenLanguages = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $learningLanguages = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $interests = null;
    // 创建时间用于显示注册日期和在推荐候选查询中排序。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, BridgeTask>
     */
    #[ORM\OneToMany(targetEntity: BridgeTask::class, mappedBy: 'author')]
    // mappedBy='author' 对应 BridgeTask::$author，表示该用户发布的全部帖子。
    private Collection $bridgeTasks;

    /**
     * @var Collection<int, TaskResponse>
     */
    #[ORM\OneToMany(targetEntity: TaskResponse::class, mappedBy: 'author')]
    private Collection $taskResponses;

    /**
     * @var Collection<int, BuddyConnection>
     */
    #[ORM\OneToMany(targetEntity: BuddyConnection::class, mappedBy: 'requester')]
    private Collection $sentBuddyConnections;

    /**
     * @var Collection<int, BuddyConnection>
     */
    #[ORM\OneToMany(targetEntity: BuddyConnection::class, mappedBy: 'receiver')]
    private Collection $receivedBuddyConnections;

    /**
     * @var Collection<int, PenpalMessage>
     */
    #[ORM\OneToMany(targetEntity: PenpalMessage::class, mappedBy: 'author')]
    private Collection $penpalMessages;
    /**
     * @var Collection<int, ResponseComment>
     */
    #[ORM\OneToMany(targetEntity: ResponseComment::class, mappedBy: 'author')]
    private Collection $responseComments;
    /**
     * @var Collection<int, TaskResponse>
     */
    #[ORM\ManyToMany(targetEntity: TaskResponse::class, mappedBy: 'likedBy')]
    private Collection $likedResponses;

    /**
     * @var Collection<int, ResponseComment>
     */
    #[ORM\ManyToMany(targetEntity: ResponseComment::class, mappedBy: 'likedBy')]
    private Collection $likedComments;

    /** @var Collection<int, BridgeTask> */
    #[ORM\ManyToMany(targetEntity: BridgeTask::class, mappedBy: 'likedBy')]
    private Collection $likedTasks;

    /** @var Collection<int, CommunityNotification> */
    #[ORM\OneToMany(targetEntity: CommunityNotification::class, mappedBy: 'recipient', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $communityNotifications;

    public function __construct()
    {
        $this->bridgeTasks = new ArrayCollection();
        $this->taskResponses = new ArrayCollection();
        $this->sentBuddyConnections = new ArrayCollection();
        $this->receivedBuddyConnections = new ArrayCollection();
        $this->penpalMessages = new ArrayCollection();
        $this->responseComments = new ArrayCollection();
        $this->likedResponses = new ArrayCollection();
        $this->likedComments = new ArrayCollection();
        $this->likedTasks = new ArrayCollection();
        $this->communityNotifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = $nationality;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * 返回适合显示在意大利语页面上的性别文字。
     */
    public function getGenderLabel(): ?string
    {
        return match ($this->gender) {
            'female' => 'Donna',
            'male' => 'Uomo',
            'other' => 'Altro',
            'prefer_not_to_say' => 'Preferisco non specificarlo',
            default => null,
        };
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    /**
     * 根据出生日期和当天日期计算年龄；没有填写时返回 null。
     */
    public function getAge(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }

        return $this->birthDate->diff(new \DateTimeImmutable('today'))->y;
    }

    public function getSpokenLanguages(): ?string
    {
        return $this->spokenLanguages;
    }

    public function setSpokenLanguages(?string $spokenLanguages): static
    {
        $this->spokenLanguages = $spokenLanguages;

        return $this;
    }

    public function getLearningLanguages(): ?string
    {
        return $this->learningLanguages;
    }

    public function setLearningLanguages(?string $learningLanguages): static
    {
        $this->learningLanguages = $learningLanguages;

        return $this;
    }

    public function getInterests(): ?string
    {
        return $this->interests;
    }

    public function setInterests(?string $interests): static
    {
        $this->interests = $interests;

        return $this;
    }

    
    public function getSpokenLanguageList(): array
    {
        return $this->splitProfileTags($this->spokenLanguages);
    }

    public function getLearningLanguageList(): array
    {
        return $this->splitProfileTags($this->learningLanguages);
    }

    public function getInterestList(): array
    {
        return $this->splitProfileTags($this->interests);
    }

    private function splitProfileTags(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $items = preg_split('/[,;，；]+/u', $value) ?: [];
        $items = array_filter(array_map('trim', $items));

        return array_values(array_unique($items));
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

    /**
     * @return Collection<int, BridgeTask>
     */
    public function getBridgeTasks(): Collection
    {
        return $this->bridgeTasks;
    }

    public function addBridgeTask(BridgeTask $bridgeTask): static
    {
        if (!$this->bridgeTasks->contains($bridgeTask)) {
            $this->bridgeTasks->add($bridgeTask);
            $bridgeTask->setAuthor($this);
        }

        return $this;
    }

    public function removeBridgeTask(BridgeTask $bridgeTask): static
    {
        if ($this->bridgeTasks->removeElement($bridgeTask)) {
            // set the owning side to null (unless already changed)
            if ($bridgeTask->getAuthor() === $this) {
                $bridgeTask->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskResponse>
     */
    public function getTaskResponses(): Collection
    {
        return $this->taskResponses;
    }

    public function addTaskResponse(TaskResponse $taskResponse): static
    {
        if (!$this->taskResponses->contains($taskResponse)) {
            $this->taskResponses->add($taskResponse);
            $taskResponse->setAuthor($this);
        }

        return $this;
    }

    public function removeTaskResponse(TaskResponse $taskResponse): static
    {
        if ($this->taskResponses->removeElement($taskResponse)) {
            // set the owning side to null (unless already changed)
            if ($taskResponse->getAuthor() === $this) {
                $taskResponse->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BuddyConnection>
     */
    public function getSentBuddyConnections(): Collection
    {
        return $this->sentBuddyConnections;
    }

    public function addSentBuddyConnection(BuddyConnection $sentBuddyConnection): static
    {
        if (!$this->sentBuddyConnections->contains($sentBuddyConnection)) {
            $this->sentBuddyConnections->add($sentBuddyConnection);
            $sentBuddyConnection->setRequester($this);
        }

        return $this;
    }

    public function removeSentBuddyConnection(BuddyConnection $sentBuddyConnection): static
    {
        if ($this->sentBuddyConnections->removeElement($sentBuddyConnection)) {
            // set the owning side to null (unless already changed)
            if ($sentBuddyConnection->getRequester() === $this) {
                $sentBuddyConnection->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BuddyConnection>
     */
    public function getReceivedBuddyConnections(): Collection
    {
        return $this->receivedBuddyConnections;
    }

    public function addReceivedBuddyConnection(BuddyConnection $receivedBuddyConnection): static
    {
        if (!$this->receivedBuddyConnections->contains($receivedBuddyConnection)) {
            $this->receivedBuddyConnections->add($receivedBuddyConnection);
            $receivedBuddyConnection->setReceiver($this);
        }

        return $this;
    }

    public function removeReceivedBuddyConnection(BuddyConnection $receivedBuddyConnection): static
    {
        if ($this->receivedBuddyConnections->removeElement($receivedBuddyConnection)) {
            // set the owning side to null (unless already changed)
            if ($receivedBuddyConnection->getReceiver() === $this) {
                $receivedBuddyConnection->setReceiver(null);
            }
        }

        return $this;
    }

    public function getUnreadPenpalMessageCount(): int
    {
        $count = 0;
        $connections = [...$this->sentBuddyConnections, ...$this->receivedBuddyConnections];

        foreach ($connections as $connection) {
            if ($connection->getStatus() !== 'accepted') {
                continue;
            }
            foreach ($connection->getMessages() as $message) {
                if ($message->getAuthor() !== $this && !$message->isRead()) {
                    ++$count;
                }
            }
        }

        return $count;
    }

    public function getPendingBuddyRequestCount(): int
    {
        return $this->receivedBuddyConnections->filter(
            static fn (BuddyConnection $connection): bool => $connection->getStatus() === 'pending'
        )->count();
    }

    public function getPenpalNotificationCount(): int
    {
        return $this->getUnreadPenpalMessageCount() + $this->getPendingBuddyRequestCount();
    }

    /**
     * @return Collection<int, PenpalMessage>
     */
    public function getPenpalMessages(): Collection
    {
        return $this->penpalMessages;
    }

    public function addPenpalMessage(PenpalMessage $penpalMessage): static
    {
        if (!$this->penpalMessages->contains($penpalMessage)) {
            $this->penpalMessages->add($penpalMessage);
            $penpalMessage->setAuthor($this);
        }

        return $this;
    }

    public function removePenpalMessage(PenpalMessage $penpalMessage): static
    {
        if ($this->penpalMessages->removeElement($penpalMessage)) {
            // set the owning side to null (unless already changed)
            if ($penpalMessage->getAuthor() === $this) {
                $penpalMessage->setAuthor(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, ResponseComment>
     */
    public function getResponseComments(): Collection
    {
        return $this->responseComments;
    }

    public function addResponseComment(ResponseComment $responseComment): static
    {
        if (!$this->responseComments->contains($responseComment)) {
            $this->responseComments->add($responseComment);
            $responseComment->setAuthor($this);
        }

        return $this;
    }

    public function removeResponseComment(ResponseComment $responseComment): static
    {
        if ($this->responseComments->removeElement($responseComment) && $responseComment->getAuthor() === $this) {
            $responseComment->setAuthor(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, TaskResponse>
     */
    public function getLikedResponses(): Collection
    {
        return $this->likedResponses;
    }

    /**
     * @return Collection<int, ResponseComment>
     */
    public function getLikedComments(): Collection
    {
        return $this->likedComments;
    }


    
    public function getLikedTasks(): Collection
    {
        return $this->likedTasks;
    }

    
    public function getCommunityNotifications(): Collection
    {
        return $this->communityNotifications;
    }

    
    public function getUnreadCommunityNotificationCount(): int
    {
        return $this->communityNotifications->filter(
            static fn (CommunityNotification $notification): bool => !$notification->isRead()
        )->count();
    }
}
