<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\Battle;
use App\Domain\Battle\Repositories\BattleRepositoryInterface;
use App\Domain\Item\Repositories\ItemRepositoryInterface;
use App\Domain\Item\ItemType;
use App\Domain\Npc\Repositories\NpcTemplateRepositoryInterface;
use App\Domain\Battle\TeamAssigner;
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

        $max = $battle->getMaxParticipants() ?? 2;

        $currentCount = count($battle->getParticipants());
        $needed = $max - $currentCount;

        if ($needed <= 0) {
            return;
        }

        $templates = collect($this->npcTemplateRepository->findAll());
        $humanoidTemplates = $templates->filter(fn($t) => $t->type === \App\Domain\Npc\NpcType::HUMANOID)->values();
        
        if ($humanoidTemplates->isEmpty()) {
            $humanoidTemplates = $templates;
        }

        if ($humanoidTemplates->isEmpty()) {
            return;
        }

        $allItems = $this->itemRepository->findAll();

        $templateCount = $humanoidTemplates->count();
        for ($i = 0; $i < $needed; $i++) {
            $template = $humanoidTemplates->get(($battle->getId() + $i) % $templateCount);
            $this->addBot($battle, $template, $allItems, $i);
        }

        $this->battleRepository->save($battle);
    }

    private function addBot(Battle $battle, $template, array $allItems, int $i): void
    {
        $team = $this->teamAssigner->assign($battle->getParticipantTeams());
        $combatantId = $this->battleRepository->generateNpcCombatantId(
            $battle->getId(),
            $template->id,
            $team
        );

        $bot = $this->npcFactory->createFromTemplate($template, $combatantId);
        
        $battle->addParticipant($bot);
        $battle->assignTeam($bot->getId(), $team);
        
        $nameParts = explode(' ', $template->name);
        $defPrefix = $nameParts[0] ?? '';
        $atkPrefix = $nameParts[1] ?? '';

        $defendArch = match ($defPrefix) {
            'Shadow' => 'dodge',
            'Balanced' => 'universal',
            default => 'tank', // Guardian
        };

        $attackArch = match ($atkPrefix) {
            'Executioner' => 'crit',
            'Versatile' => 'hybrid',
            default => 'stable', // Steadfast
        };
        
        // 5 deterministic loadout types, stable for the same battle and bot order.
        $loadoutType = (($battle->getId() + $combatantId + $i) % 5) + 1;

        $this->applyEquipment($bot, $attackArch, $defendArch, $loadoutType, $allItems);

        // Assign behavior based on loadout
        $bot->setBehaviorModelKey(
            in_array($loadoutType, [1, 2]) ? 'defensive' : 'aggressive'
        );

        // Verbose name for testing
        $offhand = $bot->getEquipment()->getItem(EquipmentSlot::OFF_HAND);
        $offhandName = $offhand ? $offhand->getName() : ($loadoutType === 5 ? '2H' : 'None');
        $statsStr = "S:{$bot->getStrength()} D:{$bot->getAgility()} C:{$bot->getConstitution()} W:{$bot->getWit()}";
        $bot->setName("{$template->name} " . ($i + 1) . " [{$statsStr}] [{$offhandName}]");
        
        $bot->initializeAdArmor();
        $bot->restoreHp();


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
            $skw = $shieldKeywords[$defendArch] ?? 'Balanced';
            $offHand = collect($allItems)->first(fn($item) => 
                $item->getItemType() === ItemType::SHIELD && 
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
        $armorKw = match ($defendArch) {
            'tank' => 'Guardian',
            'dodge' => 'Shadow',
            'universal' => 'Balanced',
            default => 'Balanced'
        };

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
                str_contains($item->getName(), $armorKw) && 
                str_contains($item->getName(), $suffix)
            );
            if ($armor) {
                $equipment->setItem($slot, $armor);
            }
        }

        // 4. Seal selection
        $seal = collect($allItems)->first(fn($item) => 
            $item->getItemType()->value === 'seal' && 
            str_contains($item->getName(), $kw)
        );
        if ($seal) {
            $equipment->setItem(EquipmentSlot::SEAL_1, $seal);
            $equipment->setItem(EquipmentSlot::SEAL_2, $seal);
            $equipment->setItem(EquipmentSlot::SEAL_3, $seal);
            $equipment->setItem(EquipmentSlot::SEAL_4, $seal);
        }
    }
}
