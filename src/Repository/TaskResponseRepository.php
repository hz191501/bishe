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
            // 统计每条回答的点赞人数；HIDDEN 让统计值只参与排序而不改变返回结构。
            ->addSelect('COUNT(likedBy.id) AS HIDDEN likeCount')
            // leftJoin 保证零点赞的回答也会出现在结果中。
            ->leftJoin('response.likedBy', 'likedBy')
            ->andWhere('response.task = :task')
            ->setParameter('task', $task)
            // 使用 COUNT 聚合后必须按回答分组，才能得到每条回答各自的点赞数。
            ->groupBy('response.id')
            ->orderBy('response.isBestAnswer', 'DESC')
            ->addOrderBy('likeCount', 'DESC')
            ->addOrderBy('response.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

}
