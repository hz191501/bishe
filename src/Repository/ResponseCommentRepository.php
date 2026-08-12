<?php

/* 回答评论仓库：负责读取和查询回答评论。 */

namespace App\Repository;

use App\Entity\ResponseComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponseComment>
 */

class ResponseCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // 当前没有自定义查询；父类已经提供按ID和条件查询评论的通用方法。
        parent::__construct($registry, ResponseComment::class);
    }
}
