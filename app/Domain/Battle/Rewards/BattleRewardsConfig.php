<?php

declare(strict_types=1);

namespace App\Domain\Battle\Rewards;

/**
 * Configuration for battle effectiveness, XP rewards, and Coin rewards.
 */
class BattleRewardsConfig
{
    /**
     * @param array<string, float> $armorKoefs
     * @param float $levelKoef
     * @param int $coinBasePerItem
     * @param array<string, int> $coinMultipliers
     * @param array<string, float> $teamCoinSplits
     */
    public function __construct(
        public readonly array $armorKoefs,
        public readonly float $levelKoef,
        public readonly int $coinBasePerItem = 10,
        public readonly array $coinMultipliers = [],
        public readonly array $teamCoinSplits = []
    ) {
    }
}
