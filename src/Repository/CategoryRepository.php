<?php

/* 分类仓库：负责从数据库读取和查询分类。 */

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */

class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        // 绑定 Category 实体；基础的 find()、findAll()、findBy() 等方法由父类自动提供。
        parent::__construct($registry, Category::class);
    }

}
