<?php

/* 通知仓库：按时间倒序读取当前用户的通知，并提供分页查询。 */

namespace App\Repository;

use App\Entity\CommunityNotification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;


class CommunityNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityNotification::class);
    }

    /** @return Paginator<CommunityNotification> */
    public function findPaginatedFor(User $user, int $page, int $perPage = 15): Paginator
    {
        // 只查询当前接收者的通知，并连接 actor 以便页面显示是谁触发了通知。
        $query = $this->createQueryBuilder('notification')
            ->addSelect('actor')
            ->join('notification.actor', 'actor')
            ->andWhere('notification.recipient = :recipient')
            ->setParameter('recipient', $user)
            ->orderBy('notification.createdAt', 'DESC')
            // 数据库层分页，避免先读取全部通知再由 PHP 截取。
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        return new Paginator($query);
    }

    public function markAllAsRead(User $user): void
    {
        /*
         * 直接执行一条 UPDATE，把当前用户所有未读通知的 readAt 设置为现在。
         * 这种批量更新比逐个加载通知实体并循环保存更简单。
         */
        $this->createQueryBuilder('notification')
            ->update()
            ->set('notification.readAt', ':now')
            ->where('notification.recipient = :recipient')
            ->andWhere('notification.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('recipient', $user)
            ->getQuery()
            ->execute();
    }

    public function removeMatching(User $recipient, User $actor, string $type, string $targetPath): void
    {
        // 取消点赞等操作发生时，按接收者、操作者、类型和目标地址精确删除对应通知。
        $this->createQueryBuilder('notification')
            ->delete()
            ->where('notification.recipient = :recipient')
            ->andWhere('notification.actor = :actor')
            ->andWhere('notification.type = :type')
            ->andWhere('notification.targetPath = :targetPath')
            // 当前 Doctrine 版本的 setParameters() 需要 ArrayCollection；
            // 这里逐个绑定参数更直观，也与本仓库其他查询的写法保持一致。
            ->setParameter('recipient', $recipient)
            ->setParameter('actor', $actor)
            ->setParameter('type', $type)
            ->setParameter('targetPath', $targetPath)
            ->getQuery()
            ->execute();
    }
}
