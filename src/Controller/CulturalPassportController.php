<?php

/*
 * 文化护照控制器：汇总用户参与过的帖子、好友和共享记录，
 * 用于生成“我的旅程”页面，不直接修改业务数据。
 */

namespace App\Controller;

use App\Entity\User;
use App\Service\BridgeJourneyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


class CulturalPassportController extends AbstractController
{
    #[Route('/passaporto-culturale', name: 'app_cultural_passport', methods: ['GET'])]
    public function index(BridgeJourneyService $journeyService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('passport/index.html.twig', [
            'passport' => $journeyService->buildPassport($user),
        ]);
    }
}
