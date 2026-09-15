<?php

/* 用户仓库：按邮箱查找用户，并根据语言、兴趣等条件生成好友推荐。 */

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // Security 组件传入的是接口类型，先确认实际对象确实是本项目的 User。
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }
        // 这里只接收已经哈希过的密码，然后立即保存；不会保存明文密码。
        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * 生成好友推荐：
     * 先查候选用户，再逐个计算匹配分数，最后返回分数最高的前 $limit 位。
     */
    public function findPenpalMatches(User $currentUser, array $excludedUserIds, int $limit = 6): array
    {
        // 不推荐自己；最多先取100名最近注册用户，避免用户量很大时计算过多。
        $builder = $this->createQueryBuilder('candidate')
            ->andWhere('candidate != :currentUser')
            ->setParameter('currentUser', $currentUser)
            ->addOrderBy('candidate.createdAt', 'DESC')
            ->setMaxResults(100);

        if ($excludedUserIds !== []) {
            // 排除已有好友和已经存在申请关系的用户，防止重复推荐。
            $builder->andWhere('candidate.id NOT IN (:excluded)')
                ->setParameter('excluded', array_values(array_unique($excludedUserIds)));
        }

        $matches = array_map(
            // 把每个 User 转换成包含 user、score、reasons 的推荐结果数组。
            fn (User $candidate): array => $this->buildMatch($currentUser, $candidate),
            $builder->getQuery()->getResult(),
        );
        // 按 score 从大到小排列，只截取页面需要展示的前6位。
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($matches, 0, $limit);
    }

    /**
     * 比较当前用户和一个候选用户，返回匹配分数与可读原因。
     * 这是简单、可解释的规则计算，分值来自明确条件。
     */
    private function buildMatch(User $current, User $candidate): array
    {
        // 每位新用户都有20分基础分，避免资料不完整时完全无法得到推荐。
        $score = 20;
        $reasons = [];
        // normalize() 把语言统一成适合比较的“标准键”，保留原文字用于页面显示。
        $currentLearning = $this->normalize($current->getLearningLanguageList());
        $currentSpoken = $this->normalize($current->getSpokenLanguageList());
        $candidateLearning = $this->normalize($candidate->getLearningLanguageList());
        $candidateSpoken = $this->normalize($candidate->getSpokenLanguageList());

        // 候选人会说的语言与当前用户想学的语言有交集时，加25分。
        $helpsCurrent = array_values(array_intersect_key($candidateSpoken, $currentLearning));
        if ($helpsCurrent !== []) {
            $score += 25;
            $reasons[] = 'Può aiutarti con '.implode(', ', $helpsCurrent);
        }
        // 当前用户也能帮助候选人学习时，再加25分，形成互相帮助的交换关系。
        $currentHelps = array_values(array_intersect_key($currentSpoken, $candidateLearning));
        if ($currentHelps !== []) {
            $score += 25;
            $reasons[] = 'Puoi aiutarlo con '.implode(', ', $currentHelps);
        }
        // 两人填写了不同国籍时增加跨文化交流分数。
        if ($current->getNationality() && $candidate->getNationality()
            && mb_strtolower($current->getNationality()) !== mb_strtolower($candidate->getNationality())) {
            $score += 20;
            $reasons[] = 'Scambio interculturale';
        }

        // 找出共同兴趣；每项加5分，但兴趣部分最多加10分，避免它压过语言匹配。
        $commonInterests = array_values(array_intersect_key(
            $this->normalize($current->getInterestList()),
            $this->normalize($candidate->getInterestList()),
        ));
        if ($commonInterests !== []) {
            $score += min(10, count($commonInterests) * 5);
            $reasons[] = 'Interessi in comune';
        }
        // 没有具体匹配原因时仍给出中性说明，不让页面出现空白。
        if ($reasons === []) {
            $reasons[] = 'Nuova persona della comunità';
        }

        return [
            'user' => $candidate,
            // 即使以后规则调整，最终分数也不会超过100%。
            'score' => min(100, $score),
            'reasons' => $reasons,
            'common_interests' => $commonInterests,
        ];
    }

    /** @param string[] $values */
    private function normalize(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            // trim 去掉首尾空格；小写形式作为键，使 Italiano 与 italiano 能匹配。
            $result[mb_strtolower(trim($value))] = trim($value);
        }
        return $result;
    }
}
