<?php

namespace App\Domain\User\Entities;

class UserEntity
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $hp = 100,
        public readonly ?int $maxHp = 100,
        public readonly ?int $strength = 10,
        public readonly ?int $dexterity = 10,
        public readonly ?string $weapon = null
    ) {
    }

    public static function create(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            hp: $data['hp'] ?? 100,
            maxHp: $data['max_hp'] ?? 100,
            strength: $data['strength'] ?? 10,
            dexterity: $data['dexterity'] ?? 10,
            weapon: $data['weapon'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'hp' => $this->hp,
            'max_hp' => $this->maxHp,
            'strength' => $this->strength,
            'dexterity' => $this->dexterity,
            'weapon' => $this->weapon,
        ];
    }
}
