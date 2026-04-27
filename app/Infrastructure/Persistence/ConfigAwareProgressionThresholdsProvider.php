<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Character\Repositories\LevelSublevelRepositoryInterface;
use App\Domain\Character\Repositories\ProgressionThresholdsProviderInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class ConfigAwareProgressionThresholdsProvider implements ProgressionThresholdsProviderInterface
{
    public function __construct(
        private readonly LevelSublevelRepositoryInterface $levelSublevelRepository,
        private readonly ConfigRepository $config,
    ) {
    }

    public function getThresholdsForLevel(int $level): array
    {
        $sublevelThresholds = $this->levelSublevelRepository->getThresholdsForLevel($level);
        if ($sublevelThresholds !== []) {
            return $sublevelThresholds;
        }

        $legacyThresholds = $this->config->get('game.xp_requirements', []);
        return is_array($legacyThresholds) ? $legacyThresholds : [];
    }
}

