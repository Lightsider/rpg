<?php

declare(strict_types=1);

namespace App\Application\Character;

use App\Domain\Character\Character;
use App\Domain\Equipment\EquipmentSlot;
use App\Domain\Weapon\Weapon;

class CharacterPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Character $character): array
    {
        $mainHand = $character->getEquipment()->getItem(EquipmentSlot::MAIN_HAND);
        $weaponName = $mainHand instanceof Weapon ? $mainHand->getName() : null;

        return [
            'id' => $character->getId(),
            'user_id' => $character->getUserId(),
            'name' => $character->getName(),
            'level' => $character->getLevel(),
            'experience' => $character->getExperience(),
            'xp_next_level' => $character->getXpNextLevel(),
            'stats' => [
                'strength' => $character->getStrength(),
                'dexterity' => $character->getAgility(),
                'constitution' => $character->getConstitution(),
                'wit' => $character->getWit(),
            ],
            'hp' => $character->getCurrentHp(),
            'max_hp' => $character->getMaxHp(),
            'location_id' => $character->getLocationId(),
            'position' => [
                'x' => $character->getX(),
                'y' => $character->getY(),
            ],
            'weapon' => $weaponName,
            'currency_copper' => $character->getCurrencyCopper(),
            'sublevel_index' => $character->getSublevelIndex(),
            'sublevels_count' => $character->getLevel() + 2,
            'additional_armor' => [
                'head' => $character->getAdArmorForZone('head'),
                'chest' => $character->getAdArmorForZone('chest'),
                'legs' => $character->getAdArmorForZone('legs'),
                'left_arm' => $character->getAdArmorForZone('left_arm'),
                'right_arm' => $character->getAdArmorForZone('right_arm'),
            ],
        ];
    }
}
