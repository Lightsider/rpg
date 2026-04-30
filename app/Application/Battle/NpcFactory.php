<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Character\CombatFormulas;
use App\Domain\Equipment\Equipment;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Npc\NpcCombatant;
use App\Domain\Npc\NpcTemplate;
use App\Domain\Npc\NpcType;
use App\Infrastructure\Eloquent\ArmorHydrator;
use App\Infrastructure\Eloquent\Models\ItemModel;
use App\Infrastructure\Eloquent\SealHydrator;
use App\Infrastructure\Eloquent\WeaponHydrator;

/**
 * Creates NpcCombatant instances from NpcTemplate definitions.
 * Handles equipment hydration for humanoid NPCs.
 */
class NpcFactory
{
    public function __construct(
        private readonly WeaponHydrator $weaponHydrator,
        private readonly ArmorHydrator $armorHydrator,
        private readonly SealHydrator $sealHydrator,
    ) {
    }

    /**
     * Create an NpcCombatant from a template, assigning a unique ID from the npcs table.
     */
    public function createFromTemplate(NpcTemplate $template, int $npcId): NpcCombatant
    {
        $equipment = new Equipment();

        if ($template->type === NpcType::HUMANOID && $template->equipmentItemIds !== null) {
            $this->hydrateEquipment($equipment, $template->equipmentItemIds);
        }

        $maxHp = $this->calculateMaxHp($template->constitution);

        $combatant = new NpcCombatant(
            id: $npcId,
            name: $template->name,
            type: $template->type,
            strength: $template->strength,
            agility: $template->agility,
            constitution: $template->constitution,
            wit: $template->wit,
            maxHp: $maxHp,
            currentHp: $maxHp,
            equipment: $equipment,
            level: $template->level,
            behaviorModelKey: $template->behaviorModel,
            npcTemplateId: $template->id,
        );

        $combatant->initializeAdArmor();

        return $combatant;
    }

    /**
     * @param array<string, int> $itemIds  Slot name => item ID mapping
     */
    private function hydrateEquipment(Equipment $equipment, array $itemIds): void
    {
        $slotMapping = [
            'main_hand' => EquipmentSlot::MAIN_HAND,
            'off_hand' => EquipmentSlot::OFF_HAND,
            'helmet' => EquipmentSlot::HELMET,
            'chest' => EquipmentSlot::CHEST,
            'legs' => EquipmentSlot::LEGS,
            'gloves' => EquipmentSlot::GLOVES,
            'seal_1' => EquipmentSlot::SEAL_1,
            'seal_2' => EquipmentSlot::SEAL_2,
            'seal_3' => EquipmentSlot::SEAL_3,
            'seal_4' => EquipmentSlot::SEAL_4,
        ];

        foreach ($itemIds as $slotName => $itemId) {
            if (!isset($slotMapping[$slotName]) || $itemId <= 0) {
                continue;
            }

            $itemModel = ItemModel::find($itemId);
            if (!$itemModel) {
                continue;
            }

            $slot = $slotMapping[$slotName];
            $item = $this->hydrateItem($itemModel, $slot);
            if ($item) {
                $equipment->setItem($slot, $item);
            }
        }
    }

    private function hydrateItem(ItemModel $itemModel, EquipmentSlot $slot): ?\App\Domain\Item\Item
    {
        return match ($itemModel->type) {
            'weapon', 'offhand_weapon' => $this->weaponHydrator->fromItem($itemModel),
            'armor', 'shield' => $this->armorHydrator->fromItem($itemModel),
            'seal' => $this->sealHydrator->fromItem($itemModel),
            default => null,
        };
    }

    private function calculateMaxHp(int $constitution): int
    {
        // Same formula as player characters: base 50 + constitution * 10
        return 50 + ($constitution * 10);
    }
}
