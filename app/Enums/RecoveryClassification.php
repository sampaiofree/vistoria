<?php

declare(strict_types=1);

namespace App\Enums;

enum RecoveryClassification: string
{
    case Total = 'total';
    case Punctual = 'punctual';
    case Opportunity = 'opportunity';

    public function label(): string
    {
        return match ($this) {
            self::Total => 'Recuperação Total',
            self::Punctual => 'Reparo Pontual',
            self::Opportunity => 'Reparo por Oportunidade',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Total => '#37174F',
            self::Punctual => '#7553CC',
            self::Opportunity => '#D8C2E8',
        };
    }

    /** @return array{code:string,label:string,color:string,text_color:string} */
    public function toArray(): array
    {
        return [
            'code' => $this->value,
            'label' => $this->label(),
            'color' => $this->color(),
            'text_color' => $this === self::Opportunity ? '#111827' : '#FFFFFF',
        ];
    }

    /** @return list<array{code:string,label:string,color:string,text_color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $option): array => $option->toArray(), self::cases());
    }
}
