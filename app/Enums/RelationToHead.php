<?php
namespace App\Enums;
enum RelationToHead: string {
    case Self='self'; case Spouse='spouse'; case Son='son'; case Daughter='daughter';
    case Father='father'; case Mother='mother'; case Grandfather='grandfather';
    case Grandmother='grandmother'; case Grandson='grandson'; case Granddaughter='granddaughter';
    case Brother='brother'; case Sister='sister'; case DaughterInLaw='daughter_in_law';
    case SonInLaw='son_in_law'; case Other='other';
    public function label(): string { return match($this){
        self::Self=>'स्वयम् (घरमूली)', self::Spouse=>'पति/पत्नी', self::Son=>'छोरा',
        self::Daughter=>'छोरी', self::Father=>'बाबु', self::Mother=>'आमा',
        self::Grandfather=>'हजुरबुवा', self::Grandmother=>'हजुरआमा',
        self::Grandson=>'नाति', self::Granddaughter=>'नातिनी',
        self::Brother=>'दाजु/भाइ', self::Sister=>'दिदी/बहिनी',
        self::DaughterInLaw=>'बुहारी', self::SonInLaw=>'ज्वाइँ', self::Other=>'अन्य',
    }; }
    public static function options(): array { return array_map(fn($c)=>['value'=>$c->value,'label'=>$c->label()],self::cases()); }
}
