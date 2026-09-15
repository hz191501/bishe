<?php

/* 帖子仓库：集中编写帖子搜索、筛选、分页和用户参与记录的数据库查询。 */

namespace App\Repository;

use App\Entity\BridgeTask;
use App\Entity\TaskResponse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class BridgeTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // 告诉父类：这个 Repository 专门操作 BridgeTask 实体及其数据表。
        parent::__construct($registry, BridgeTask::class);
    }

    /**
     * 根据关键词、状态和分类查询帖子，并只返回指定页。
     * 返回 Paginator 的好处是 Controller 既能遍历当前页，也能 count() 得到总数量。
     */
    public function searchPaginated(
        ?string $query,
        ?string $status,
        ?int $categoryId,
        int $page,
        int $perPage = 9,
    ): Paginator {
        // task 是 BridgeTask 的查询别名；同时连接作者和分类，避免模板逐条补查数据库。
        $builder = $this->createQueryBuilder('task')
            ->addSelect('author', 'category')
            ->join('task.author', 'author')
            ->join('task.category', 'category')
            ->orderBy('task.createdAt', 'DESC');

        // 有关键词时同时搜索标题和正文；LOWER() 使英文大小写不影响匹配。
        if ($query) {
            $builder
                ->andWhere('LOWER(task.title) LIKE :query OR LOWER(task.description) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($query).'%');
        }

        // 只接受程序定义的两个状态，忽略用户手动构造的非法状态参数。
        if (in_array($status, ['open', 'resolved'], true)) {
            $builder
                ->andWhere('task.status = :status')
                ->setParameter('status', $status);
        }

        // 选择分类后，通过已连接的 category.id 缩小查询范围。
        if ($categoryId) {
            $builder
                ->andWhere('category.id = :category')
                ->setParameter('category', $categoryId);
        }

        // 偏移量=(页码-1)×每页数量；例如第2页每页9条，从第9条之后开始取。
        $queryObject = $builder
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($queryObject);
    }

    /**
     * 获取某位用户发布的帖子，并按照发布时间从新到旧分页。
     */
    public function findPublishedByUserPaginated(User $user, int $page, int $perPage = 6): Paginator
    {
        // 只保留作者为目标用户的帖子，并预先连接分类供个人主页卡片显示。
        $query = $this->createQueryBuilder('task')
            ->addSelect('category')
            ->join('task.category', 'category')
            ->andWhere('task.author = :user')
            ->setParameter('user', $user)
            ->orderBy('task.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query);
    }

    /**
     * 获取某位用户参与过的讨论，每个帖子只显示一次，并按最近参与时间分页。
     */
    public function findParticipatedByUserPaginated(User $user, int $page, int $perPage = 6): Paginator
    {
        /*
         * 用相关子查询取得用户在每个讨论中的最后回答时间。
         * 这样每个帖子天然只返回一次，也能兼容启用 ONLY_FULL_GROUP_BY 的 MySQL。
         */
        $lastParticipation = $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(participation.createdAt)')
            ->from(TaskResponse::class, 'participation')
            ->andWhere('participation.task = task')
            ->andWhere('participation.author = :user');

        $participationExists = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(TaskResponse::class, 'participation_check')
            ->andWhere('participation_check.task = task')
            ->andWhere('participation_check.author = :user');

        $builder = $this->createQueryBuilder('task');
        $query = $builder
            ->addSelect('category')
            ->addSelect(sprintf('(%s) AS HIDDEN lastParticipation', $lastParticipation->getDQL()))
            ->join('task.category', 'category')
            // 排除用户自己发布的帖子，避免与“发布的帖子”区域重复。
            ->andWhere('task.author != :user')
            ->andWhere($builder->expr()->exists($participationExists->getDQL()))
            ->setParameter('user', $user)
            ->orderBy('lastParticipation', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
        ;

        return new Paginator($query);
    }
}
