<?php
namespace App\Enums;
enum GenderType: string {
    case Male='male'; case Female='female'; case Other='other';
    public function label(): string { return match($this){ self::Male=>'पुरुष', self::Female=>'महिला', self::Other=>'अन्य' }; }
    public static function options(): array { return array_map(fn($c)=>['value'=>$c->value,'label'=>$c->label()],self::cases()); }
}
