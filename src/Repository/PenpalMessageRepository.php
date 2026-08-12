<?php

/* 好友信件仓库：查询某段好友关系中的信件和最近聊天记录。 */

namespace App\Repository;

use App\Entity\BuddyConnection;
use App\Entity\PenpalMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PenpalMessage>
 */

class PenpalMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PenpalMessage::class);
    }

    /**
     * 按时间顺序读取一段好友关系中的指定一页信件。
     */
    public function findForConnectionPaginated(
        BuddyConnection $connection,
        int $page,
        int $perPage = 30,
    ): array {
        // 只读取属于当前好友关系的信件，并连带读取作者，减少模板访问作者时的额外查询。
        return $this->createQueryBuilder('message')
            ->addSelect('author')
            ->join('message.author', 'author')
            ->andWhere('message.connection = :connection')
            ->setParameter('connection', $connection)
            // 聊天内容按最早到最新排列，阅读顺序符合普通对话习惯。
            ->orderBy('message.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }
}
