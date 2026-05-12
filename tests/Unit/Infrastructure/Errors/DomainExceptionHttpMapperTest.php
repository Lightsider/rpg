<?php

namespace Tests\Unit\Infrastructure\Errors;

use App\Infrastructure\Errors\DomainExceptionHttpMapper;
use PHPUnit\Framework\TestCase;

class DomainExceptionHttpMapperTest extends TestCase
{
    public function test_maps_with_and_without_trailing_period(): void
    {
        $this->assertSame(404, DomainExceptionHttpMapper::toStatusCode('Character not found'));
        $this->assertSame(404, DomainExceptionHttpMapper::toStatusCode('Character not found.'));
    }

    public function test_maps_battle_access_errors_to_403(): void
    {
        $this->assertSame(403, DomainExceptionHttpMapper::toStatusCode('You are not a participant in this battle.'));
        $this->assertSame(403, DomainExceptionHttpMapper::toStatusCode('You are not a participant in this battle'));
    }

    public function test_maps_conflict_errors_to_409(): void
    {
        $this->assertSame(409, DomainExceptionHttpMapper::toStatusCode('Round has expired.'));
        $this->assertSame(409, DomainExceptionHttpMapper::toStatusCode('You cannot change locations while in a fight'));
    }

    public function test_defaults_to_422_for_unknown_messages(): void
    {
        $this->assertSame(422, DomainExceptionHttpMapper::toStatusCode('Some unknown domain error'));
    }
}

