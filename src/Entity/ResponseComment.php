<?php

/* 回答评论实体：保存回答下面的补充讨论、作者、时间和点赞关系。 */

namespace App\Entity;

use App\Repository\ResponseCommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ResponseCommentRepository::class)]

class ResponseComment
{
    // 评论的自动主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 评论正文不能为空，长度限制比正式回答更短。
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Scrivi un commento.')]
    #[Assert\Length(min: 2, max: 1000)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // author 指出评论作者，response 指出评论属于哪一条回答。
    #[ORM\ManyToOne(inversedBy: 'responseComments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TaskResponse $response = null;
    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'likedComments')]
    // 中间表只保存评论ID和用户ID，实现多人点赞同一评论。
    #[ORM\JoinTable(name: 'response_comment_like')]
    private Collection $likedBy;


    public function __construct()
    {
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

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

    public function getResponse(): ?TaskResponse
    {
        return $this->response;
    }

    public function setResponse(?TaskResponse $response): static
    {
        $this->response = $response;

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
        // 添加前先判断，避免同一用户在对象集合中重复点赞。
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
