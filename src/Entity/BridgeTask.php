<?php

/* 帖子实体：保存标题、内容、状态、分类、作者、回答和点赞等帖子数据。 */

namespace App\Entity;

use App\Repository\BridgeTaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BridgeTaskRepository::class)]
class BridgeTask
{
    // Id 是数据库主键；GeneratedValue 表示新增记录时由数据库自动生成，不由表单填写。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Column 定义数据库列；Assert 定义表单提交后执行的业务验证。
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Inserisci un titolo.')]
    #[Assert\Length(min: 5, max: 150)]
    private ?string $title = null;

    // TEXT 适合较长正文；这里要求正文在20到5000字符之间。
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Descrivi la tua richiesta.')]
    #[Assert\Length(min: 20, max: 5000)]
    private ?string $description = null;

    // status 保存 open 或 resolved，用于区分开放帖子和已解决帖子。
    #[ORM\Column(length: 30)]
    private ?string $status = null;

    // DateTimeImmutable 创建后不会在原对象上被修改，适合记录固定时间点。
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // nullable=true 表示尚未编辑或解决时，数据库允许该列为 NULL。
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    // 多个帖子可以属于一个作者；nullable=false 要求每个帖子必须有作者。
    #[ORM\ManyToOne(inversedBy: 'bridgeTasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    // 多个帖子可以属于同一个分类，对应 Category::$bridgeTasks 的另一端。
    #[ORM\ManyToOne(inversedBy: 'bridgeTasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    /**
     * @var Collection<int, TaskResponse>
     */
    #[ORM\OneToMany(targetEntity: TaskResponse::class, mappedBy: 'task', orphanRemoval: true)]
    // 读取集合时让最佳回答优先，其余回答按时间从新到旧。
    #[ORM\OrderBy(['isBestAnswer' => 'DESC', 'createdAt' => 'DESC'])]
    private Collection $responses;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'likedTasks')]
    // 多对多关系需要中间表，表中保存“哪个用户点赞了哪个帖子”。
    #[ORM\JoinTable(name: 'bridge_task_like')]
    private Collection $likedBy;

    public function __construct()
    {
        // Doctrine 的集合属性必须先初始化，否则调用 add()/contains() 会发生未初始化错误。
        $this->responses = new ArrayCollection();
        $this->likedBy = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        // 返回 $this 支持 $task->setTitle(...)->setStatus(...) 这样的链式调用。
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, TaskResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(TaskResponse $response): static
    {
        // contains() 防止同一个回答对象被重复加入集合。
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            // 同步维护关系的拥有端，保证 response.task 与当前帖子一致。
            $response->setTask($this);
        }

        return $this;
    }

    public function removeResponse(TaskResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            // set the owning side to null (unless already changed)
            if ($response->getTask() === $this) {
                $response->setTask(null);
            }
        }

        return $this;
    }

    
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
