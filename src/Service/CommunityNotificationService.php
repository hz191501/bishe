<?php

/*
 * 社区通知服务：
 * Controller 在发生“点赞、回答、评论、好友申请”等事件时调用这里。
 * 这个类只负责组织并保存通知，避免每个 Controller 重复同一段代码。
 * 注意：create() 只执行 persist()，真正写入数据库通常由调用方统一 flush()。
 */

namespace App\Service;

use App\Entity\CommunityNotification;
use App\Entity\User;
use App\Repository\CommunityNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;


class CommunityNotificationService
{
    /*
     * 构造器注入两个依赖：
     * - EntityManagerInterface：把新通知加入 Doctrine 的保存队列；
     * - CommunityNotificationRepository：查询或移除已经存在的通知。
     * private readonly 表示依赖只能在构造时赋值，之后不能被替换。
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommunityNotificationRepository $notifications,
    ) {
    }

    public function create(User $recipient, User $actor, string $type, string $message, string $targetPath): void
    {
        // recipient 是接收者，actor 是触发操作的人；自己操作时不需要通知自己。
        if ($recipient === $actor) {
            return;
        }

        /*
         * 创建通知实体，并使用链式 setter 一次填入：
         * 接收者、操作者、事件类型、显示文字、点击后的地址和创建时间。
         */
        $notification = (new CommunityNotification())
            ->setRecipient($recipient)
            ->setActor($actor)
            ->setType($type)
            ->setMessage($message)
            ->setTargetPath($targetPath)
            ->setCreatedAt(new \DateTimeImmutable());

        // persist() 让 Doctrine 开始管理该对象；此处故意不 flush，方便外层一次提交全部修改。
        $this->entityManager->persist($notification);
    }

    public function remove(User $recipient, User $actor, string $type, string $targetPath): void
    {
        // 例如用户取消点赞时，按四个条件找到对应通知并删除，避免留下失效提醒。
        $this->notifications->removeMatching($recipient, $actor, $type, $targetPath);
    }
}
