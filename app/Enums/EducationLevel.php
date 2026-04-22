<?php
namespace App\Enums;
enum EducationLevel: string {
    case None='none'; case PrePrimary='pre_primary'; case Primary='primary';
    case LowerSecondary='lower_secondary'; case Secondary='secondary'; case Slc='slc';
    case Proficiency='proficiency'; case Bachelor='bachelor'; case Master='master'; case Phd='phd';
    public function label(): string { return match($this){
        self::None=>'अशिक्षित', self::PrePrimary=>'प्री-प्राइमरी',
        self::Primary=>'प्राथमिक (कक्षा १-५)', self::LowerSecondary=>'निम्न माध्यमिक (६-८)',
        self::Secondary=>'माध्यमिक (९-१०)', self::Slc=>'एसईई/SLC',
        self::Proficiency=>'प्रवीणता (PCL/+२)', self::Bachelor=>'स्नातक',
        self::Master=>'स्नातकोत्तर', self::Phd=>'विद्यावारिधि',
    }; }
    public static function options(): array { return array_map(fn($c)=>['value'=>$c->value,'label'=>$c->label()],self::cases()); }
}
