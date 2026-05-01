<?php

namespace App\Imports;

use App\Imports\Sheets\HouseholdsSheet;
use App\Imports\Sheets\MembersSheet;
use App\Imports\Sheets\HousingSheet;
use App\Imports\Sheets\UtilitiesSheet;
use App\Imports\Sheets\EconomicSheet;
use App\Imports\Sheets\AgricultureSheet;
use App\Imports\Sheets\LivestockSheet;
use App\Services\SurveyValueValidator;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HouseholdImport implements WithMultipleSheets
{
    private HouseholdsSheet $householdsSheet;
    private array $dependentSheets = [];

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $wardId = null,
        private readonly ?SurveyValueValidator $validator = null,
    ) {
        $this->householdsSheet = new HouseholdsSheet($enumeratorId, $wardId);

        $this->dependentSheets = [
            'Members'     => new MembersSheet($this->householdsSheet,    $this->validator),
            'Housing'     => new HousingSheet($this->householdsSheet,    $this->validator),
            'Utilities'   => new UtilitiesSheet($this->householdsSheet,  $this->validator),
            'Economic'    => new EconomicSheet($this->householdsSheet,   $this->validator),
            'Agriculture' => new AgricultureSheet($this->householdsSheet),
            'Livestock'   => new LivestockSheet($this->householdsSheet),
        ];
    }

    public function sheets(): array
    {
        return array_merge(
            ['Households' => $this->householdsSheet],
            $this->dependentSheets
        );
    }

    public function getErrors(): array
    {
        $all = [];
        $allSheets = array_merge(['Households' => $this->householdsSheet], $this->dependentSheets);
        foreach ($allSheets as $name => $sheet) {
            foreach ($sheet->getErrors() as $err) {
                if (is_array($err)) {
                    $err['sheet'] = $name;
                    $all[] = $err;
                } else {
                    $all[] = "[{$name}] {$err}";
                }
            }
        }
        return $all;
    }

    public function getTotalHouseholds(): int
    {
        return $this->householdsSheet->getImportedCount();
    }
}
