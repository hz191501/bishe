<?php

/*
 * 首页控制器：准备首页需要的统计数字、分类和最新帖子，
 * 再把这些数据交给首页 Twig 模板显示。
 */

namespace App\Controller;

use App\Repository\BridgeTaskRepository;
use App\Repository\CategoryRepository;
use App\Repository\TaskResponseRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    
    #[Route('/', name: 'app_home')]
    public function index(
        BridgeTaskRepository $bridgeTaskRepository,
        TaskResponseRepository $taskResponseRepository,
        UserRepository $userRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        return $this->render('home/index.html.twig', [
            'latest_tasks' => $bridgeTaskRepository->findBy([], ['createdAt' => 'DESC'], 3),
            'categories' => $categoryRepository->findBy([], ['name' => 'ASC']),
            'task_count' => $bridgeTaskRepository->count([]),
            'response_count' => $taskResponseRepository->count([]),
            'user_count' => $userRepository->count([]),
        ]);
    }
}
