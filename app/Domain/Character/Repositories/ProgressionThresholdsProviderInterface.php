<?php

declare(strict_types=1);

namespace App\Domain\Character\Repositories;

interface ProgressionThresholdsProviderInterface
{
    /**
     * @return array<int, array{xp_threshold: int, reward_copper: int}>|array<int, int>
     */
    public function getThresholdsForLevel(int $level): array;
}

