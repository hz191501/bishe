<?php

/* 帖子回答实体：保存回答内容、作者、最佳回答状态、评论和点赞。 */

namespace App\Entity;

use App\Repository\TaskResponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TaskResponseRepository::class)]

class TaskResponse
{
    // 回答记录的自动主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 回答使用 TEXT 长文本，并通过 Assert 控制10到5000字符。
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Scrivi una risposta.')]
    #[Assert\Length(min: 10, max: 5000)]
    private ?string $content = null;

    // 帖子作者可以把其中一条回答标记为最佳回答。
    #[ORM\Column]
    private ?bool $isBestAnswer = null;

    // createdAt 必填，updatedAt 只有回答被编辑后才有值。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // 多条回答可以属于同一个作者或同一个帖子，两处外键都不能为空。
    #[ORM\ManyToOne(inversedBy: 'taskResponses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?BridgeTask $task = null;
    /**
     * @var Collection<int, ResponseComment>
     */
    #[ORM\OneToMany(targetEntity: ResponseComment::class, mappedBy: 'response', orphanRemoval: true)]
    // 删除回答时，同时删除失去归属的评论。
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $comments;
    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'likedResponses')]
    // task_response_like 是回答与点赞用户之间的中间表。
    #[ORM\JoinTable(name: 'task_response_like')]
    private Collection $likedBy;


    public function __construct()
    {
        // 集合属性先初始化为空集合，后续才能安全 add/remove。
        $this->comments = new ArrayCollection();
        $this->likedBy = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function isBestAnswer(): ?bool
    {
        return $this->isBestAnswer;
    }

    public function setIsBestAnswer(bool $isBestAnswer): static
    {
        $this->isBestAnswer = $isBestAnswer;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getTask(): ?BridgeTask
    {
        return $this->task;
    }

    public function setTask(?BridgeTask $task): static
    {
        $this->task = $task;

        return $this;
    }
    /**
     * @return Collection<int, ResponseComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(ResponseComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setResponse($this);
        }

        return $this;
    }

    public function removeComment(ResponseComment $comment): static
    {
        if ($this->comments->removeElement($comment) && $comment->getResponse() === $this) {
            $comment->setResponse(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getLikedBy(): Collection
    {
        return $this->likedBy;
    }

    public function addLike(User $user): static
    {
        if (!$this->likedBy->contains($user)) {
            $this->likedBy->add($user);
        }

        return $this;
    }

    public function removeLike(User $user): static
    {
        $this->likedBy->removeElement($user);

        return $this;
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likedBy->contains($user);
    }

    public function getLikeCount(): int
    {
        return $this->likedBy->count();
    }

}
