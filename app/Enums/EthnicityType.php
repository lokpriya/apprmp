<?php
namespace App\Enums;
enum EthnicityType: string {
    case BrahminHill='brahmin_hill'; case Chhetri='chhetri'; case Magar='magar';
    case Tamang='tamang'; case Newar='newar'; case Tharu='tharu';
    case Kami='kami'; case Damai='damai'; case Sarki='sarki';
    case Musahar='musahar'; case Yadav='yadav'; case MadhesiBrahmin='madhesi_brahmin';
    case OtherDalit='other_dalit'; case OtherJanajati='other_janajati'; case Other='other';
    public function label(): string { return match($this){
        self::BrahminHill=>'ब्राह्मण (पहाड)', self::Chhetri=>'क्षेत्री', self::Magar=>'मगर',
        self::Tamang=>'तामाङ', self::Newar=>'नेवार', self::Tharu=>'थारू',
        self::Kami=>'कामी', self::Damai=>'दमाई', self::Sarki=>'सार्की',
        self::Musahar=>'मुसहर', self::Yadav=>'यादव', self::MadhesiBrahmin=>'ब्राह्मण (मधेस)',
        self::OtherDalit=>'अन्य दलित', self::OtherJanajati=>'अन्य जनजाति', self::Other=>'अन्य',
    }; }
    public static function options(): array { return array_map(fn($c)=>['value'=>$c->value,'label'=>$c->label()],self::cases()); }
}
