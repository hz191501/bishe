<?php

/* 好友关系仓库：查询两人的关系、好友列表以及待处理申请。 */

namespace App\Repository;

use App\Entity\BuddyConnection;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class BuddyConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuddyConnection::class);
    }

    public function findBetween(User $first, User $second): ?BuddyConnection
    {
        /*
         * 好友关系没有固定方向：A申请B或B申请A都代表同一对用户。
         * OR 条件同时检查两个方向，可用于阻止重复申请并查找现有关系。
         */
        return $this->createQueryBuilder('connection')
            ->andWhere(
                '(connection.requester = :first AND connection.receiver = :second)'
                .' OR (connection.requester = :second AND connection.receiver = :first)'
            )
            ->setParameter('first', $first)
            ->setParameter('second', $second)
            ->orderBy('connection.createdAt', 'DESC')
            // 正常情况下只有一条；限制为1也能避免历史异常数据造成多结果错误。
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAcceptedFor(User $user): array
    {
        // 查出用户位于申请方或接收方、并且状态已经接受的全部好友关系。
        return $this->createQueryBuilder('connection')
            ->andWhere('connection.requester = :user OR connection.receiver = :user')
            ->andWhere('connection.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            // 最近建立的好友关系排在前面；Controller 还会根据未读信件进一步排序。
            ->orderBy('connection.acceptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
