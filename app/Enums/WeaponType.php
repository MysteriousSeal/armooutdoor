<?php

namespace App\Enums;

enum WeaponType: string
{
    case Pistol = 'pistol';
    case Revolver = 'revolver';
    case Rifle = 'rifle';
    case AssaultRifle = 'assault_rifle';
    case Shotgun = 'shotgun';

    public function label(): string
    {
        return match ($this) {
            self::Pistol => 'Pistol',
            self::Revolver => 'Revolver',
            self::Rifle => 'Bolt action rifle',
            self::AssaultRifle => 'Assault rifle',
            self::Shotgun => 'Shotgun',
        };
    }
}
