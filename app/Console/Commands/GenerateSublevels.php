<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Infrastructure\Eloquent\Models\LevelSublevelModel;

class GenerateSublevels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-sublevels {--levels=50 : Number of levels to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and lock in the global sublevel thresholds and rewards.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $levelsToGen = (int) $this->option('levels');
        $xpReqs = config('game.xp_requirements', []);
        $levelKoef = (float) config('game.level_koef', 1.5);
        $rewardMult = (float) config('game.sublevels.reward_multiplier', 0.5);
        
        $this->info("Generating sublevels for $levelsToGen levels...");

        $currentThreshold = 0;
        $prevStepXp = 1200; // Base step XP for Level 1

        for ($level = 1; $level <= $levelsToGen; $level++) {
            // Determine total XP span for this level (XP needed to reach level+1)
            if (isset($xpReqs[$level + 1])) {
                $totalXpForThisLevel = $xpReqs[$level + 1] - $currentThreshold;
            } else {
                // If not in config, use the geometric progression
                $totalXpForThisLevel = (int) round($prevStepXp * ($level === 1 ? 1 : $levelKoef));
            }
            
            $numSublevels = $level + 2;
            $totalWeights = ($numSublevels * ($numSublevels + 1)) / 2;
            $xpUnit = $totalXpForThisLevel / $totalWeights;
            
            $accumulatedXpForLevel = 0;
            
            for ($sl = 1; $sl <= $numSublevels; $sl++) {
                $stepXp = (int) round($xpUnit * $sl);
                $accumulatedXpForLevel += $stepXp;
                
                // Absolute threshold
                $absoluteThreshold = $currentThreshold + $accumulatedXpForLevel;
                
                // If it's the last sublevel, ensure it matches the next level boundary perfectly
                if ($sl === $numSublevels) {
                     $absoluteThreshold = $currentThreshold + $totalXpForThisLevel;
                }

                $reward = (int) round($stepXp * $rewardMult);
                
                LevelSublevelModel::updateOrCreate(
                    ['level' => $level, 'sublevel_index' => $sl],
                    [
                        'xp_threshold' => $absoluteThreshold, 
                        'reward_copper' => $reward
                    ]
                );
            }
            
            $prevStepXp = $totalXpForThisLevel;
            $currentThreshold += $totalXpForThisLevel;
        }

        $this->info("Done! Sublevel configuration locked in level_sublevels table.");
    }
}
