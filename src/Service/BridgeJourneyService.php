<?php

/*
 * 交流旅程服务：
 * 根据已经保存在 Entity 集合中的帖子、回答、好友、信件和共享笔记，
 * 计算文化护照徽章以及一段好友关系的发展摘要。
 * 它不直接写数据库，只把现有数据整理成 Twig 容易展示的数组。
 */

namespace App\Service;

use App\Entity\BuddyConnection;
use App\Entity\User;


class BridgeJourneyService
{
    /**
     * 为一个用户生成“文化护照”。
     * 返回值包含全部徽章、已解锁数量和完成比例。
     */
    public function buildPassport(User $user): array
    {
        // Doctrine Collection 转成普通数组，方便使用 array_filter、count 和排序函数。
        $tasks = $user->getBridgeTasks()->toArray();
        $responses = $user->getTaskResponses()->toArray();
        // 合并发出和收到的关系，只保留状态为 accepted 的真正好友。
        $connections = array_values(array_filter(
            array_merge(
                $user->getSentBuddyConnections()->toArray(),
                $user->getReceivedBuddyConnections()->toArray(),
            ),
            static fn (BuddyConnection $connection): bool => $connection->getStatus() === 'accepted',
        ));

        $messages = [];
        $notes = [];
        $mutualNoteDates = [];
        foreach ($connections as $connection) {
            // 个人徽章只统计本人发送的信件和本人记录的笔记。
            foreach ($connection->getMessages() as $message) {
                if ($message->getAuthor() === $user) {
                    $messages[] = $message;
                }
            }

            $friend = $connection->getRequester() === $user
                ? $connection->getReceiver()
                : $connection->getRequester();
            $ownNotes = [];
            $friendNotes = [];
            foreach ($connection->getSharedNotes() as $note) {
                if ($note->getAuthor() === $user) {
                    $notes[] = $note;
                    $ownNotes[] = $note;
                } elseif ($friend !== null && $note->getAuthor() === $friend) {
                    $friendNotes[] = $note;
                }
                // 补充留言也属于共同贡献，但不增加个人笔记数量或笔记类型徽章。
                foreach ($note->getComments() as $comment) {
                    if ($comment->getAuthor() === $user) {
                        $ownNotes[] = $comment;
                    } elseif ($friend !== null && $comment->getAuthor() === $friend) {
                        $friendNotes[] = $comment;
                    }
                }
            }
            // 同一本笔记里双方都参与后解锁，日期取较晚一方的首次贡献。
            if ($ownNotes !== [] && $friendNotes !== []) {
                $mutualNoteDates[] = max($this->firstDate($ownNotes), $this->firstDate($friendNotes));
            }
        }

        // 按时间累计不同类型，第三种类型首次出现时才解锁。
        usort($notes, static fn ($a, $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());
        $noteTypes = [];
        $thirdTypeDate = null;
        foreach ($notes as $note) {
            $noteTypes[$note->getType()] = true;
            if (count($noteTypes) >= 3) {
                $thirdTypeDate = $note->getCreatedAt();
                break;
            }
        }
        $mutualNoteDate = $mutualNoteDates !== [] ? min($mutualNoteDates) : null;

        $bestResponses = array_values(array_filter(
            $responses,
            // 严格等于 true，避免 null 被误判为最佳回答。
            static fn ($response): bool => $response->isBestAnswer() === true,
        ));
        $taskConnections = array_values(array_filter(
            $connections,
            static fn (BuddyConnection $connection): bool => $connection->getSourceTask() !== null,
        ));
        $monthConnections = array_values(array_filter(
            $connections,
            // acceptedAt 存在且距今至少30天，才满足“一个月好友”的条件。
            static fn (BuddyConnection $connection): bool => $connection->getAcceptedAt() !== null
                && $connection->getAcceptedAt()->diff(new \DateTimeImmutable())->days >= 30,
        ));

        $badges = [
            // 每条规则调用 badge() 生成相同结构，Twig 可以用同一个循环统一显示。
            $this->badge('first_task', 'Primo passo', 'Aiuto', 'Hai pubblicato la prima richiesta.', '01', count($tasks) >= 1, $this->firstDate($tasks)),
            $this->badge('first_answer', 'Una mano tesa', 'Aiuto', 'Hai condiviso la prima risposta.', '02', count($responses) >= 1, $this->firstDate($responses)),
            $this->badge('best_answer', 'Risposta preziosa', 'Aiuto', 'Una tua risposta è stata scelta come migliore.', '03', count($bestResponses) >= 1, $this->firstDate($bestResponses)),
            $this->badge('first_friend', 'Primo ponte', 'Amicizia', 'Hai stretto la prima amicizia di penna.', '04', count($connections) >= 1, $this->firstAcceptedDate($connections)),
            $this->badge('task_friend', 'Nati da un aiuto', 'Amicizia', 'Un BridgeTask si è trasformato in amicizia.', '05', count($taskConnections) >= 1, $this->firstAcceptedDate($taskConnections)),
            $this->badge('month_friend', 'Un mese insieme', 'Amicizia', 'Una vostra amicizia dura da almeno 30 giorni.', '06', count($monthConnections) >= 1, $this->firstAcceptedDate($monthConnections)),
            $this->badge('first_letter', 'Prima lettera', 'Lettere', 'Hai inviato la prima lettera digitale.', '07', count($messages) >= 1, $this->firstDate($messages)),
            $this->badge('five_letters', 'Dialogo continuo', 'Lettere', 'Hai scritto almeno 5 lettere.', '08', count($messages) >= 5, $this->dateAtPosition($messages, 5)),
            $this->badge('ten_letters', 'Corrispondenza viva', 'Lettere', 'Hai scritto almeno 10 lettere.', '09', count($messages) >= 10, $this->dateAtPosition($messages, 10)),
            $this->badge('first_note', 'Prima scoperta', 'Cultura', 'Hai salvato la prima nota culturale.', '10', count($notes) >= 1, $this->firstDate($notes)),
            $this->badge('three_cultures', 'Esploratore culturale', 'Cultura', 'Hai raccolto note di almeno 3 tipi.', '11', $thirdTypeDate !== null, $thirdTypeDate),
            $this->badge('mutual_notes', 'Due voci, due culture', 'Cultura', 'Tu e un amico avete contribuito allo stesso quaderno.', '12', $mutualNoteDate !== null, $mutualNoteDate),
        ];

        $unlocked = count(array_filter($badges, static fn (array $badge): bool => $badge['unlocked']));

        return [
            'badges' => $badges,
            'groups' => ['Aiuto', 'Amicizia', 'Lettere', 'Cultura'],
            'total_count' => count($badges),
            'unlocked_count' => $unlocked,
            // round 后转为整数，页面显示整洁的完成百分比。
            'percentage' => (int) round($unlocked / count($badges) * 100),
        ];
    }

    /**
     * 汇总一段好友关系的信件数量、笔记数量、持续天数和最近一封信。
     */
    public function buildConnectionJourney(BuddyConnection $connection): array
    {
        $letterCount = $connection->getMessages()->count();
        $noteCount = $connection->getSharedNotes()->count();
        $acceptedAt = $connection->getAcceptedAt();
        // 尚未接受的申请没有 acceptedAt，因此持续天数按0处理。
        $daysTogether = $acceptedAt ? (int) $acceptedAt->diff(new \DateTimeImmutable())->format('%a') : 0;
        $lastLetter = $connection->getMessages()->last() ?: null;

        return [
            'letter_count' => $letterCount,
            'note_count' => $noteCount,
            'last_letter' => $lastLetter,
            'days_together' => $daysTogether,
            'growth' => $this->buildGrowth(
                $letterCount,
                $noteCount,
                $daysTogether,
                $connection->getSourceTask() !== null,
            ),
        ];
    }

    /**
     * 把通信活动换算成页面上的旅程等级。
     * 这只是展示用的确定性规则，不会改变用户权限或好友排序。
     */
    private function buildGrowth(int $letterCount, int $noteCount, int $daysTogether, bool $fromTask): array
    {
        // 每种活动使用固定权重，便于解释和后续修改。
        $sources = [
            'letters' => $letterCount * 10,
            'notes' => $noteCount * 30,
            'days' => $daysTogether * 2,
            'bridge_task' => $fromTask ? 50 : 0,
        ];
        $totalXp = array_sum($sources);
        // 每125点进入下一等级；intdiv 只取整数商，% 得到当前等级内的进度。
        $levelSize = 125;
        $level = intdiv($totalXp, $levelSize) + 1;
        $currentXp = $totalXp % $levelSize;
        $remainingXp = $levelSize - $currentXp;

        return [
            'level' => $level,
            'title' => $this->growthTitle($level),
            'total_xp' => $totalXp,
            'current_xp' => $currentXp,
            'next_level_xp' => $levelSize,
            'remaining_xp' => $remainingXp,
            'percentage' => (int) round($currentXp / $levelSize * 100),
            'source_counts' => [
                'letters' => $letterCount,
                'notes' => $noteCount,
                'days' => $daysTogether,
                'bridge_task' => $fromTask ? 1 : 0,
            ],
            'sources' => $sources,
        ];
    }

    private function growthTitle(int $level): string
    {
        // match(true) 从上到下选择第一个满足条件的等级称号。
        return match (true) {
            $level >= 40 => 'Ambasciatori dell’amicizia',
            $level >= 20 => 'Ponte culturale',
            $level >= 10 => 'Amici di penna',
            $level >= 5 => 'Compagni di scambio',
            default => 'Primo incontro',
        };
    }

    /** @return array<string, mixed> */
    private function badge(
        string $key,
        string $title,
        string $group,
        string $description,
        string $mark,
        bool $unlocked,
        ?\DateTimeImmutable $date,
    ): array {
        // 所有徽章都使用相同键名，模板不需要了解每项规则的计算细节。
        return [
            'key' => $key,
            'title' => $title,
            'group' => $group,
            'description' => $description,
            'mark' => $mark,
            'unlocked' => $unlocked,
            'date' => $date,
        ];
    }

    /** @param array<int, object> $items */
    private function firstDate(array $items): ?\DateTimeImmutable
    {
        // 空数组没有解锁日期，返回 null 让 Twig 不显示日期。
        if ($items === []) {
            return null;
        }
        // 按创建时间升序排列，第一项就是最早完成该行为的时间。
        usort($items, static fn ($a, $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $items[0]->getCreatedAt();
    }

    /** @param array<int, object> $items */
    private function dateAtPosition(array $items, int $position): ?\DateTimeImmutable
    {
        // 项目数量不足时，表示相应“第N次”徽章尚未解锁。
        if (count($items) < $position) {
            return null;
        }
        usort($items, static fn ($a, $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());

        // 数组从0开始计数，因此第N项的索引是 N-1。
        return $items[$position - 1]->getCreatedAt();
    }

    /** @param array<int, BuddyConnection> $connections */
    private function firstAcceptedDate(array $connections): ?\DateTimeImmutable
    {
        if ($connections === []) {
            return null;
        }
        usort(
            $connections,
            // 找到这些好友关系中最早的接受时间。
            static fn (BuddyConnection $a, BuddyConnection $b): int => $a->getAcceptedAt() <=> $b->getAcceptedAt(),
        );

        return $connections[0]->getAcceptedAt();
    }
}
