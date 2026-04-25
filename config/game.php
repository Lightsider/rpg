<?php

return [
    'stat_pool_level_1' => 16,
    'core_stat_ratio' => 0.25,
    'base_hp' => 55,
    'hp_per_con' => 8.5,
    'xp_requirements' => [
        2 => 1200,
    ],
    'armor_koef' => [
        'tank' => 1.0,
        'universal' => 1.16,
        'dodge' => 1.38,
        'non_armor' => 1.0,
    ],
    'level_koef' => 1.5,
    'rewards' => [
        'coins' => [
            'base_per_item' => 10,
            'multipliers' => [
                'body' => 2,
                'weapon_1h' => 2,
                'weapon_2h' => 3,
            ],
            'team_split' => [
                'winner' => 0.7,
                'loser' => 0.3,
            ],
        ],
    ],
    'sublevels' => [
        'reward_multiplier' => 0.5,
    ],
];
