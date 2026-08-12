<?php

/* 分类实体：保存分类名称，并关联属于该分类的帖子。 */

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]

class Category
{
    // 分类的自动递增主键。
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // 分类名称最多100字符，对应管理员分类表单的 maxlength。
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    /**
     * @var Collection<int, BridgeTask>
     */
    #[ORM\OneToMany(targetEntity: BridgeTask::class, mappedBy: 'category')]
    // mappedBy 表示真正保存外键的是 BridgeTask::$category。
    private Collection $bridgeTasks;

    public function __construct()
    {
        $this->bridgeTasks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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
        // 同时更新集合和帖子上的 category，保持双向对象关系一致。
        if (!$this->bridgeTasks->contains($bridgeTask)) {
            $this->bridgeTasks->add($bridgeTask);
            $bridgeTask->setCategory($this);
        }

        return $this;
    }

    public function removeBridgeTask(BridgeTask $bridgeTask): static
    {
        if ($this->bridgeTasks->removeElement($bridgeTask)) {
            // 只有帖子仍然指向当前分类时才清空，避免覆盖它刚被设置的新分类。
            if ($bridgeTask->getCategory() === $this) {
                $bridgeTask->setCategory(null);
            }
        }

        return $this;
    }
}
