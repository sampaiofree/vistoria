<?php

declare(strict_types=1);

namespace App\Enums;

enum DefectCategory: string
{
    case Civil = 'CV';
    case AnticorrosiveTreatment = 'TAC';
    case StructuralRecovery = 'REC';

    public function label(): string
    {
        return match ($this) {
            self::Civil => 'CIVIL',
            self::AnticorrosiveTreatment => 'TAC',
            self::StructuralRecovery => 'REC',
        };
    }

    public function code(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::Civil => 'Avarias relacionadas aos elementos civis.',
            self::AnticorrosiveTreatment => 'Avarias relacionadas ao tratamento anticorrosivo.',
            self::StructuralRecovery => 'Avarias relacionadas à recuperação estrutural.',
        };
    }

    public function position(): int
    {
        return match ($this) {
            self::Civil => 1,
            self::AnticorrosiveTreatment => 2,
            self::StructuralRecovery => 3,
        };
    }

    /** @return array{code:string,name:string,description:string,position:int} */
    public function toArray(): array
    {
        return [
            'code' => $this->value,
            'name' => $this->label(),
            'description' => $this->description(),
            'position' => $this->position(),
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                ...$case->toArray(),
            ],
            self::cases(),
        );
    }
}
