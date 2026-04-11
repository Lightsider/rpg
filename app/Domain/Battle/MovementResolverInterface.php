<?php

declare(strict_types=1);

namespace App\Domain\Battle;

interface MovementResolverInterface
{
    public function resolveMovement(Battle $battle): void;
}
