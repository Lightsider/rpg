<?php

declare(strict_types=1);

namespace App\Domain\Npc\Behavior;

/**
 * Registry that maps behavior model keys (strings) to BehaviorModelInterface instances.
 * Allows extensible registration of custom behaviors for different NPC types/archetypes.
 */
class BehaviorModelRegistry
{
    /** @var array<string, BehaviorModelInterface> */
    private array $models = [];

    /**
     * Register a behavior model under a given key.
     */
    public function register(string $key, BehaviorModelInterface $model): void
    {
        $this->models[$key] = $model;
    }

    /**
     * Retrieve a behavior model by key. Falls back to 'reach_and_hit' if not found.
     */
    public function get(string $key): BehaviorModelInterface
    {
        if (isset($this->models[$key])) {
            return $this->models[$key];
        }

        if (isset($this->models['reach_and_hit'])) {
            return $this->models['reach_and_hit'];
        }

        throw new \RuntimeException("Behavior model '{$key}' not registered and no default available.");
    }

    /**
     * Check if a behavior model with the given key exists.
     */
    public function has(string $key): bool
    {
        return isset($this->models[$key]);
    }
}
