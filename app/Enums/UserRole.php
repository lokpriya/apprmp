<?php
namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin      = 'super_admin';
    case MunicipalAdmin  = 'municipal_admin';
    case WardReviewer    = 'ward_reviewer';
    case Enumerator      = 'enumerator';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin     => 'सुपर प्रशासक',
            self::MunicipalAdmin => 'नगर प्रशासक',
            self::WardReviewer   => 'वडा समीक्षक',
            self::Enumerator     => 'गणक',
        };
    }

    public static function options(): array
    {
        return array_map(fn($c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
