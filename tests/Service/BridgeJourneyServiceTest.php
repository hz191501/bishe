<?php

namespace App\Tests\Service;

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
}
