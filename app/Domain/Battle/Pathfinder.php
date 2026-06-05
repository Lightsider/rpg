<?php

declare(strict_types=1);

namespace App\Domain\Battle;

/**
 * BFS-based pathfinder for the battle grid.
 *
 * Finds the shortest path between two cells on the map, navigating around
 * occupied cells. Returns the first step the unit should take.
 */
class Pathfinder
{
    /**
     * 8-directional movement offsets (including diagonals).
     */
    private const array DIRECTIONS = [
        [-1, -1], [-1, 0], [-1, 1],
        [0, -1],           [0, 1],
        [1, -1],  [1, 0],  [1, 1],
    ];

    /**
     * Find the first step of the shortest path from ($fromX, $fromY) to ($toX, $toY).
     *
     * Returns the coordinates of the next cell to move to, or null if no path exists.
     *
     * @param callable(int $x, int $y): bool $isBlocked A function returning true if the cell cannot be entered.
     * @return array{x: int, y: int}|null
     */
    public static function findNextStep(
        Map $map,
        int $fromX,
        int $fromY,
        int $toX,
        int $toY,
        callable $isBlocked
    ): ?array {
        // Already there
        if ($fromX === $toX && $fromY === $toY) {
            return null;
        }

        // Already adjacent – just go there directly if not blocked
        if ($map->isAdjacent($fromX, $fromY, $toX, $toY) && !$isBlocked($toX, $toY)) {
            return ['x' => $toX, 'y' => $toY];
        }

        // BFS
        $queue = [[$fromX, $fromY]];
        // $cameFrom stores "which cell did we come from" keyed by "x:y"
        $cameFrom = [];
        $startKey = "$fromX:$fromY";
        $cameFrom[$startKey] = null;

        while (!empty($queue)) {
            [$cx, $cy] = array_shift($queue);

            foreach (self::DIRECTIONS as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }

                $key = "$nx:$ny";
                if (isset($cameFrom[$key])) {
                    continue;
                }

                // Destination is always reachable (we want to path TO it, not through it)
                $isDestination = ($nx === $toX && $ny === $toY);

                if (!$isDestination && $isBlocked($nx, $ny)) {
                    continue;
                }

                $cameFrom[$key] = "$cx:$cy";

                if ($isDestination) {
                    return self::traceFirstStep($cameFrom, $key, $startKey);
                }

                $queue[] = [$nx, $ny];
            }
        }

        // No path found – return null
        return null;
    }

    /**
     * Find the first step of the shortest path from ($fromX, $fromY) to
     * ANY of the given goal cells. Returns the step for whichever goal
     * is reached first (i.e. the closest reachable goal).
     *
     * @param array<int, array{x: int, y: int}> $goals
     * @param callable(int $x, int $y): bool $isBlocked
     * @return array{x: int, y: int, goalX: int, goalY: int}|null
     */
    public static function findNextStepToAny(
        Map $map,
        int $fromX,
        int $fromY,
        array $goals,
        callable $isBlocked
    ): ?array {
        if (empty($goals)) {
            return null;
        }

        // Build a set of goal keys for O(1) lookup
        $goalSet = [];
        foreach ($goals as $g) {
            $goalSet[$g['x'] . ':' . $g['y']] = $g;
        }

        // Check if we're already at a goal
        $startKey = "$fromX:$fromY";
        if (isset($goalSet[$startKey])) {
            return null; // already there
        }

        // Check if any goal is directly adjacent and walkable
        foreach ($goals as $g) {
            if ($map->isAdjacent($fromX, $fromY, $g['x'], $g['y']) && !$isBlocked($g['x'], $g['y'])) {
                return ['x' => $g['x'], 'y' => $g['y'], 'goalX' => $g['x'], 'goalY' => $g['y']];
            }
        }

        // BFS
        $queue = [[$fromX, $fromY]];
        $cameFrom = [];
        $cameFrom[$startKey] = null;

        while (!empty($queue)) {
            [$cx, $cy] = array_shift($queue);

            foreach (self::DIRECTIONS as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }

                $key = "$nx:$ny";
                if (isset($cameFrom[$key])) {
                    continue;
                }

                $isGoal = isset($goalSet[$key]);

                if (!$isGoal && $isBlocked($nx, $ny)) {
                    continue;
                }

                $cameFrom[$key] = "$cx:$cy";

                if ($isGoal) {
                    $step = self::traceFirstStep($cameFrom, $key, $startKey);
                    if ($step !== null) {
                        $step['goalX'] = $nx;
                        $step['goalY'] = $ny;
                    }
                    return $step;
                }

                $queue[] = [$nx, $ny];
            }
        }

        return null;
    }

    /**
     * Compute the BFS distance from ($fromX, $fromY) to ($toX, $toY).
     * Returns PHP_INT_MAX if no path exists.
     *
     * @param callable(int $x, int $y): bool $isBlocked
     */
    public static function distance(
        Map $map,
        int $fromX,
        int $fromY,
        int $toX,
        int $toY,
        callable $isBlocked
    ): int {
        if ($fromX === $toX && $fromY === $toY) {
            return 0;
        }

        $queue = [[$fromX, $fromY, 0]];
        $visited = ["$fromX:$fromY" => true];

        while (!empty($queue)) {
            [$cx, $cy, $dist] = array_shift($queue);

            foreach (self::DIRECTIONS as [$dx, $dy]) {
                $nx = $cx + $dx;
                $ny = $cy + $dy;

                if (!$map->isWithinBounds($nx, $ny)) {
                    continue;
                }

                $key = "$nx:$ny";
                if (isset($visited[$key])) {
                    continue;
                }

                if ($nx === $toX && $ny === $toY) {
                    return $dist + 1;
                }

                if ($isBlocked($nx, $ny)) {
                    continue;
                }

                $visited[$key] = true;
                $queue[] = [$nx, $ny, $dist + 1];
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * Trace back through the cameFrom map to find the very first step from the start.
     *
     * @param array<string, string|null> $cameFrom
     * @return array{x: int, y: int}|null
     */
    private static function traceFirstStep(array $cameFrom, string $endKey, string $startKey): ?array
    {
        $current = $endKey;

        while (true) {
            $parent = $cameFrom[$current] ?? null;
            if ($parent === null || $parent === $startKey) {
                // $current is the first step
                [$x, $y] = explode(':', $current);
                return ['x' => (int) $x, 'y' => (int) $y];
            }
            $current = $parent;
        }
    }
}
