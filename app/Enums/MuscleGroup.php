<?php

namespace App\Enums;

enum MuscleGroup: string
{
    case Chest = 'chest';
    case Back = 'back';
    case Shoulders = 'shoulders';
    case Arms = 'arms';
    case Legs = 'legs';
    case Glutes = 'glutes';
    case Core = 'core';
    case FullBody = 'full_body';
    case Cardio = 'cardio';
    case Mobility = 'mobility';

    public function label(): string
    {
        return match ($this) {
            self::Chest => 'Στήθος',
            self::Back => 'Πλάτη',
            self::Shoulders => 'Ώμοι',
            self::Arms => 'Χέρια',
            self::Legs => 'Πόδια',
            self::Glutes => 'Γλουτοί',
            self::Core => 'Κορμός',
            self::FullBody => 'Όλο το σώμα',
            self::Cardio => 'Καρδιο',
            self::Mobility => 'Κινητικότητα',
        };
    }
}
