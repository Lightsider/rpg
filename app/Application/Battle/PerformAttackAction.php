<?php

declare(strict_types=1);

namespace App\Application\Battle;

use App\Domain\Battle\AttackResult;
use App\Domain\Battle\CombatResolver;
use App\Domain\Character\Repositories\CharacterRepositoryInterface;
use Exception;

/**
 * Application service to perform an attack between two characters.
 */
class PerformAttackAction
{
    private const int MIN_HP = 0;

    public function __construct(
        private readonly CharacterRepositoryInterface $characterRepository,
        private readonly CombatResolver $combatResolver
    ) {
    }

    /**
     * Executes the attack logic.
     *
     * @throws Exception If attacker or defender not found.
     */
    public function execute(int $attackerId, int $defenderId, bool $isBlocked): AttackResult
    {
        // 1. Load attacker and defender
        $attacker = $this->characterRepository->findById($attackerId);
        $defender = $this->characterRepository->findById($defenderId);

        if (!$attacker || !$defender) {
            throw new Exception('Attacker or defender not found.');
        }

        // 2. Call CombatResolver
        $result = $this->combatResolver->resolveAttack($attacker, $defender, $isBlocked);

        // 3. If damage > 0, update defender HP
        if ($result->damage > 0) {
            $newHp = max(self::MIN_HP, $defender->getCurrentHp() - $result->damage);
            $defender->setCurrentHp($newHp);

            // 4. Persist updated defender HP
            $this->characterRepository->updateHp($defenderId, $newHp);
        }

        return $result;
    }
}
