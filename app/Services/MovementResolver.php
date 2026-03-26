<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Battle\Battle;
use App\Domain\Battle\TargetZone;
use App\Domain\DomainException;
use App\Infrastructure\Eloquent\Models\FighterPositionModel;
use Illuminate\Support\Facades\DB;

class MovementResolver
{
    /**
     * @param array<int, array<string, mixed>> $moveActions
     */
    public function validateMovementActions(Battle $battle, array $moveActions): void
    {
        $map = $battle->getMap();

        foreach ($moveActions as $action) {
            $characterId = $action['character_id'];
            $character = $battle->getParticipantById($characterId);
            if (!$character) {
                throw new DomainException('Character not found in this battle.');
            }

            $toX = (int) $action['to_x'];
            $toY = (int) $action['to_y'];

            if (!$map->isWithinBounds($toX, $toY)) {
                throw new DomainException('Target cell is outside the map.');
            }

            $dx = abs($character->getX() - $toX);
            $dy = abs($character->getY() - $toY);
            if ($dx > 1 || $dy > 1) {
                throw new DomainException('Target cell is not adjacent.');
            }

            $blocks = $action['blocks'] ?? [];
            $uniqueBlocks = array_unique($blocks);
            if (count($blocks) !== count($uniqueBlocks)) {
                throw new DomainException('Duplicate block zones are not allowed.');
            }

            foreach ($blocks as $zone) {
                TargetZone::from($zone);
            }
        }
    }

    public function resolveMovement(Battle $battle): void
    {
        $moveActions = [];
        foreach ($battle->getQueuedActions() as $action) {
            if ($action->getType()->value !== 'move') {
                continue;
            }

            $moveActions[] = [
                'character_id' => $action->getCharacterId(),
                'to_x' => $action->getToX(),
                'to_y' => $action->getToY(),
                'blocks' => $action->getBlocks(),
            ];
        }

        if (count($moveActions) === 0) {
            return;
        }

        $this->validateMovementActions($battle, $moveActions);

        $currentPositions = [];
        foreach ($battle->getParticipants() as $participant) {
            $currentPositions[$participant->getId()] = [
                'x' => $participant->getX(),
                'y' => $participant->getY(),
            ];
        }

        $targetsByCharacter = [];
        foreach ($moveActions as $action) {
            $targetsByCharacter[$action['character_id']] = [
                'x' => (int) $action['to_x'],
                'y' => (int) $action['to_y'],
            ];
        }

        $movingIds = array_keys($targetsByCharacter);
        $validTargets = [];
        foreach ($targetsByCharacter as $characterId => $target) {
            $occupiedBy = $this->findOccupantId($target, $currentPositions);
            if ($occupiedBy !== null && !in_array($occupiedBy, $movingIds, true)) {
                continue;
            }
            $validTargets[$characterId] = $target;
        }

        $resolvedMoves = [];
        $processed = [];

        foreach ($validTargets as $characterId => $target) {
            if (isset($processed[$characterId])) {
                continue;
            }

            $swapPartner = $this->findSwapPartner($characterId, $target, $currentPositions, $validTargets);
            if ($swapPartner !== null) {
                $resolvedMoves[$characterId] = $validTargets[$characterId];
                $resolvedMoves[$swapPartner] = $validTargets[$swapPartner];
                $processed[$characterId] = true;
                $processed[$swapPartner] = true;
            }
        }

        $movesByTarget = [];
        foreach ($validTargets as $characterId => $target) {
            if (isset($resolvedMoves[$characterId])) {
                continue;
            }

            $key = $target['x'] . ':' . $target['y'];
            $movesByTarget[$key][] = $characterId;
        }

        foreach ($movesByTarget as $characterIds) {
            sort($characterIds, SORT_NUMERIC);
            $winnerId = $characterIds[0];
            $resolvedMoves[$winnerId] = $validTargets[$winnerId];
        }

        DB::transaction(function () use ($battle, $resolvedMoves) {
            foreach ($resolvedMoves as $characterId => $target) {
                $tempX = -1;
                $tempY = -1 - $characterId;
                FighterPositionModel::where('fight_id', $battle->getId())
                    ->where('character_id', $characterId)
                    ->update(['x' => $tempX, 'y' => $tempY]);
            }

            foreach ($resolvedMoves as $characterId => $target) {
                $participant = $battle->getParticipantById($characterId);
                if ($participant) {
                    $participant->setPosition($target['x'], $target['y']);
                }

                FighterPositionModel::where('fight_id', $battle->getId())
                    ->where('character_id', $characterId)
                    ->update(['x' => $target['x'], 'y' => $target['y']]);
            }
        });
    }

    /**
     * @param array{x:int, y:int} $target
     * @param array<int, array{x:int, y:int}> $currentPositions
     */
    private function findOccupantId(array $target, array $currentPositions): ?int
    {
        foreach ($currentPositions as $id => $pos) {
            if ($pos['x'] === $target['x'] && $pos['y'] === $target['y']) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{x:int, y:int}> $currentPositions
     * @param array<int, array{x:int, y:int}> $targetsByCharacter
     */
    private function findSwapPartner(
        int $characterId,
        array $target,
        array $currentPositions,
        array $targetsByCharacter
    ): ?int {
        foreach ($targetsByCharacter as $otherId => $otherTarget) {
            if ($otherId === $characterId) {
                continue;
            }

            $current = $currentPositions[$characterId] ?? null;
            $otherCurrent = $currentPositions[$otherId] ?? null;
            if (!$current || !$otherCurrent) {
                continue;
            }

            if ($target['x'] === $otherCurrent['x'] && $target['y'] === $otherCurrent['y']
                && $otherTarget['x'] === $current['x'] && $otherTarget['y'] === $current['y']) {
                return $otherId;
            }
        }

        return null;
    }
}

