<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
use App\Services\TeamAssigner;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\Weapon;
use App\Domain\Armor\Armor;
use App\Domain\Armor\ArmorSubtype;
use App\Domain\Armor\Shield;

class BotFillingService
{
    public function __construct(
        private readonly BattleRepositoryInterface $battleRepository,
        private readonly NpcTemplateRepositoryInterface $npcTemplateRepository,
        private readonly ItemRepositoryInterface $itemRepository,
        private readonly NpcFactory $npcFactory,
        private readonly TeamAssigner $teamAssigner
    ) {
    }

    public function fillBattle(Battle $battle): void
    {
        if (!$battle->shouldFillWithBots()) {
            return;
        }

        $max = $battle->getMaxParticipants();
        if ($max === null) {
            return;
        }

        $currentCount = count($battle->getParticipants());
        $needed = $max - $currentCount;

        if ($needed <= 0) {
            return;
        }

        $templates = $this->npcTemplateRepository->findAll();
        $humanoidTemplate = collect($templates)->first(fn($t) => $t->type === \App\Domain\Npc\NpcType::HUMANOID);
        
        if (!$humanoidTemplate) {
            $humanoidTemplate = collect($templates)->first();
        }

        if (!$humanoidTemplate) {
            return;
        }

        $allItems = $this->itemRepository->findAll();

        for ($i = 0; $i < $needed; $i++) {
            $this->addBot($battle, $humanoidTemplate, $allItems);
        }

        $this->battleRepository->save($battle);
    }

    private function addBot(Battle $battle, $template, array $allItems): void
    {
        $combatantId = $this->battleRepository->generateNpcCombatantId(
            $battle->getId(),
            $template->id,
            $this->teamAssigner->assign($battle->getParticipantTeams())
        );

        $bot = $this->npcFactory->createFromTemplate($template, $combatantId);
        
        // 3 Attack Archetypes: Stable, Crit, Hybrid
        $attackArch = ['stable', 'crit', 'hybrid'][rand(0, 2)];
        
        // 3 Defend Archetypes: Tank, Dodge, Universal
        $defendArch = ['tank', 'dodge', 'universal'][rand(0, 2)];
        
        // 5 Loadout Types
        $loadoutType = rand(1, 5);

        $this->applyStats($bot, $attackArch, $defendArch);
        $this->applyEquipment($bot, $attackArch, $defendArch, $loadoutType, $allItems);
        
        $bot->initializeAdArmor();
        $bot->restoreHp();

        $battle->addParticipant($bot);
    }

    private function applyStats($bot, string $attackArch, string $defendArch): void
    {
        $stats = ['strength' => 4, 'dexterity' => 4, 'constitution' => 4, 'wit' => 4];
        
        $reqs = [
            'stable' => ['strength' => 8],
            'crit' => ['wit' => 4, 'strength' => 4],
            'hybrid' => ['strength' => 6, 'wit' => 2],
            'tank' => ['constitution' => 8],
            'dodge' => ['dexterity' => 4, 'constitution' => 4],
            'universal' => ['constitution' => 6, 'dexterity' => 2],
        ];

        foreach ($reqs[$attackArch] as $stat => $val) {
            $stats[$stat] = max($stats[$stat], $val);
        }
        foreach ($reqs[$defendArch] as $stat => $val) {
            $stats[$stat] = max($stats[$stat], $val);
        }

        $totalUsed = array_sum($stats);
        $available = 16; // Base points at level 1
        
        if ($totalUsed < $available) {
            // Put remaining points into primary attack stat
            $primary = match($attackArch) {
                'stable' => 'strength',
                'crit' => 'wit',
                'hybrid' => 'strength',
                default => 'strength'
            };
            $stats[$primary] += ($available - $totalUsed);
        }

        $bot->setStats($stats['strength'], $stats['dexterity'], $stats['constitution'], $stats['wit']);
    }

    private function applyEquipment($bot, string $attackArch, string $defendArch, int $loadoutType, array $allItems): void
    {
        $equipment = $bot->getEquipment();

        // 1. Weapon selection
        $weaponKeywords = [
            'stable' => 'Steadfast',
            'crit' => 'Executioner',
            'hybrid' => 'Versatile'
        ];
        
        $kw = $weaponKeywords[$attackArch];
        
        if ($loadoutType === 5) { // 2H Axe
            $mainHand = collect($allItems)->first(fn($item) => 
                $item instanceof Weapon && 
                str_contains($item->getName(), $kw) && 
                str_contains($item->getName(), 'Battleaxe')
            );
        } elseif ($loadoutType === 1 || $loadoutType === 3) { // Axe
            $mainHand = collect($allItems)->first(fn($item) => 
                $item instanceof Weapon && 
                str_contains($item->getName(), $kw) && 
                str_contains($item->getName(), 'Axe') && 
                !str_contains($item->getName(), 'Battleaxe')
            );
        } else { // Sword
            $mainHand = collect($allItems)->first(fn($item) => 
                $item instanceof Weapon && 
                str_contains($item->getName(), $kw) && 
                str_contains($item->getName(), 'Sword')
            );
        }

        if ($mainHand) {
            $equipment->setItem(EquipmentSlot::MAIN_HAND, $mainHand);
        }

        // 2. Off-hand selection
        if ($loadoutType === 1 || $loadoutType === 2) { // Shield
            $shieldKeywords = [
                'tank' => 'Guardian',
                'dodge' => 'Shadow',
                'universal' => 'Balanced'
            ];
            $skw = $shieldKeywords[$defendArch];
            $offHand = collect($allItems)->first(fn($item) => 
                $item instanceof Shield && 
                str_contains($item->getName(), $skw)
            );
            if ($offHand) {
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offHand);
            }
        } elseif ($loadoutType === 3 || $loadoutType === 4) { // Dagger
            $offHand = collect($allItems)->first(fn($item) => 
                $item instanceof Weapon && 
                str_contains($item->getName(), $kw) && 
                str_contains($item->getName(), 'Dagger')
            );
            if ($offHand) {
                $equipment->setItem(EquipmentSlot::OFF_HAND, $offHand);
            }
        }

        // 3. Armor selection
        $armorKeywords = [
            'tank' => 'Guardian',
            'dodge' => 'Shadow',
            'universal' => 'Balanced'
        ];
        $akw = $armorKeywords[$defendArch];

        $slots = [
            EquipmentSlot::HELMET->value => 'Helmet',
            EquipmentSlot::CHEST->value => 'Chestplate',
            EquipmentSlot::LEGS->value => 'Boots',
            EquipmentSlot::GLOVES->value => 'Gauntlets'
        ];

        foreach ($slots as $slotKey => $suffix) {
            $slot = EquipmentSlot::from($slotKey);
            $armor = collect($allItems)->first(fn($item) => 
                $item instanceof Armor && 
                str_contains($item->getName(), $akw) && 
                str_contains($item->getName(), $suffix)
            );
            if ($armor) {
                $equipment->setItem($slot, $armor);
            }
        }

        // 4. Seal selection
        $seal = collect($allItems)->first(fn($item) => 
            $item->getType()->value === 'seal' && 
            str_contains($item->getName(), $kw)
        );
        if ($seal) {
            $equipment->setItem(EquipmentSlot::SEAL_1, $seal);
        }
    }
}
