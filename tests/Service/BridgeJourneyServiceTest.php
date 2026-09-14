<?php

namespace App\Tests\Service;

use App\Entity\BuddyConnection;
use App\Entity\PenpalMessage;
use App\Entity\SharedNote;
use App\Entity\User;
use App\Service\BridgeJourneyService;
use PHPUnit\Framework\TestCase;

final class BridgeJourneyServiceTest extends TestCase
{
    public function testEmptyPassportContainsCompleteProgressSummary(): void
    {
        $passport = (new BridgeJourneyService())->buildPassport(new User());
        self::assertSame(12, $passport['total_count']);
        self::assertSame(0, $passport['unlocked_count']);
        self::assertSame(0, $passport['percentage']);
        self::assertCount(12, $passport['badges']);
        self::assertSame(['Aiuto', 'Amicizia', 'Lettere', 'Cultura'], $passport['groups']);
    }

    public function testReceivedLettersDoNotUnlockSentLetterBadges(): void
    {
        $user = new User();
        $friend = new User();
        $connection = $this->connect($user, $friend);
        for ($day = 1; $day <= 10; ++$day) {
            $connection->addMessage((new PenpalMessage())->setAuthor($friend)->setCreatedAt(new \DateTimeImmutable('2026-07-'.$day)));
        }
        self::assertFalse($this->badges($user)['first_letter']['unlocked']);
        for ($day = 11; $day <= 15; ++$day) {
            $connection->addMessage((new PenpalMessage())->setAuthor($user)->setCreatedAt(new \DateTimeImmutable('2026-07-'.$day)));
        }
        $badges = $this->badges($user);
        self::assertTrue($badges['five_letters']['unlocked']);
        self::assertFalse($badges['ten_letters']['unlocked']);
        self::assertEquals(new \DateTimeImmutable('2026-07-15'), $badges['five_letters']['date']);
        self::assertSame(15, (new BridgeJourneyService())->buildConnectionJourney($connection)['letter_count']);
    }

    public function testCultureBadgeRequiresThreeDistinctOwnNoteTypes(): void
    {
        $user = new User();
        $friend = new User();
        $connection = $this->connect($user, $friend);
        $this->note($connection, $friend, 'culture', '2026-07-01');
        self::assertFalse($this->badges($user)['first_note']['unlocked']);
        foreach (['2026-07-02', '2026-07-03', '2026-07-04'] as $date) {
            $this->note($connection, $user, 'word', $date);
        }
        self::assertFalse($this->badges($user)['three_cultures']['unlocked']);
        $this->note($connection, $user, 'food', '2026-07-06');
        self::assertFalse($this->badges($user)['three_cultures']['unlocked']);
        $this->note($connection, $user, 'place', '2026-07-08');
        $badge = $this->badges($user)['three_cultures'];
        self::assertTrue($badge['unlocked']);
        self::assertEquals(new \DateTimeImmutable('2026-07-08'), $badge['date']);
    }

    public function testMutualNotesMustBelongToTheSameNotebook(): void
    {
        $user = new User();
        $firstFriend = new User();
        $secondFriend = new User();
        $first = $this->connect($user, $firstFriend);
        $second = $this->connect($user, $secondFriend);
        $this->note($first, $user, 'word', '2026-07-02');
        $this->note($second, $secondFriend, 'food', '2026-07-03');
        self::assertFalse($this->badges($user)['mutual_notes']['unlocked']);
        $this->note($first, $firstFriend, 'culture', '2026-07-05');
        $badge = $this->badges($user)['mutual_notes'];
        self::assertTrue($badge['unlocked']);
        self::assertEquals(new \DateTimeImmutable('2026-07-05'), $badge['date']);
    }

    public function testPendingRelationshipsDoNotCountTowardsPassport(): void
    {
        $user = new User();
        $connection = $this->connect($user, new User())->setStatus('pending');
        $connection->addMessage((new PenpalMessage())->setAuthor($user)->setCreatedAt(new \DateTimeImmutable('2026-07-01')));
        $this->note($connection, $user, 'word', '2026-07-02');
        self::assertSame(0, (new BridgeJourneyService())->buildPassport($user)['unlocked_count']);
    }

    private function connect(User $user, User $friend): BuddyConnection
    {
        $connection = (new BuddyConnection())->setReceiver($friend)->setStatus('accepted')
            ->setCreatedAt(new \DateTimeImmutable('2026-07-01'))->setAcceptedAt(new \DateTimeImmutable());
        $user->addSentBuddyConnection($connection);
        return $connection;
    }

    private function note(BuddyConnection $connection, User $author, string $type, string $date): void
    {
        $connection->addSharedNote((new SharedNote())->setAuthor($author)->setType($type)->setCreatedAt(new \DateTimeImmutable($date)));
    }

    private function badges(User $user): array
    {
        return array_column((new BridgeJourneyService())->buildPassport($user)['badges'], null, 'key');
    }
}
