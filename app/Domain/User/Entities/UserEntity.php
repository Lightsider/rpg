<?php

namespace App\Domain\User\Entities;

class UserEntity
{
    public const int DEFAULT_HP = 100;
    public const int DEFAULT_STRENGTH = 10;
    public const int DEFAULT_DEXTERITY = 10;

    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?int $hp = self::DEFAULT_HP,
        public readonly ?int $maxHp = self::DEFAULT_HP,
        public readonly ?int $strength = self::DEFAULT_STRENGTH,
        public readonly ?int $dexterity = self::DEFAULT_DEXTERITY,
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
            hp: $data['hp'] ?? self::DEFAULT_HP,
            maxHp: $data['max_hp'] ?? self::DEFAULT_HP,
            strength: $data['strength'] ?? self::DEFAULT_STRENGTH,
            dexterity: $data['dexterity'] ?? self::DEFAULT_DEXTERITY,
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
