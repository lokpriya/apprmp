<?php
namespace App\Enums;
enum OccupationType: string {
    case Agriculture='agriculture'; case Business='business';
    case ServiceGovt='service_govt'; case ServicePrivate='service_private';
    case Student='student'; case HouseholdWork='household_work';
    case ForeignEmployment='foreign_employment'; case DailyWage='daily_wage';
    case Unemployed='unemployed'; case Other='other';
    public function label(): string { return match($this){
        self::Agriculture=>'कृषि', self::Business=>'व्यापार/व्यवसाय',
        self::ServiceGovt=>'सरकारी सेवा', self::ServicePrivate=>'निजी सेवा',
        self::Student=>'विद्यार्थी', self::HouseholdWork=>'घरायसी काम',
        self::ForeignEmployment=>'वैदेशिक रोजगार', self::DailyWage=>'ज्यालादारी',
        self::Unemployed=>'बेरोजगार', self::Other=>'अन्य',
    }; }
    public static function options(): array { return array_map(fn($c)=>['value'=>$c->value,'label'=>$c->label()],self::cases()); }
}
