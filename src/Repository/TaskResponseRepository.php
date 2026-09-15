<?php

/* 帖子回答仓库：查询回答，并让最佳回答和较新的回答优先显示。 */

namespace App\Repository;

use App\Entity\BridgeTask;
use App\Entity\TaskResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskResponse>
 */
class TaskResponseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskResponse::class);
    }

    /**
     * 读取一个帖子的回答并分页。
     * 排序优先级依次是：最佳回答、点赞数量、发布时间。
     */
    public function findForTaskOrderedPaginated(
        BridgeTask $task,
        int $page,
        int $perPage = 10,
    ): array
    {
        return $this->createQueryBuilder('response')
            // SIZE 读取关联集合的数量，不需要 GROUP BY，也兼容 MySQL 的严格模式。
            ->addSelect('SIZE(response.likedBy) AS HIDDEN likeCount')
            ->andWhere('response.task = :task')
            ->setParameter('task', $task)
            ->orderBy('response.isBestAnswer', 'DESC')
            ->addOrderBy('likeCount', 'DESC')
            ->addOrderBy('response.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

}
