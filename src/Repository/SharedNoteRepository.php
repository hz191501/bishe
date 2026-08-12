<?php

/* 共享笔记仓库：查询某个好友关系中的共同笔记。 */

namespace App\Repository;

use App\Entity\SharedNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SharedNote> */
class SharedNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // 绑定 SharedNote 实体，Controller 可使用 findBy() 查询某段好友关系的共享笔记。
        parent::__construct($registry, SharedNote::class);
    }
}
