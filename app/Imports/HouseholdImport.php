<?php

namespace App\Imports;

use App\Imports\Sheets\HouseholdsSheet;
use App\Imports\Sheets\MembersSheet;
use App\Imports\Sheets\HousingSheet;
use App\Imports\Sheets\UtilitiesSheet;
use App\Imports\Sheets\EconomicSheet;
use App\Imports\Sheets\AgricultureSheet;
use App\Imports\Sheets\LivestockSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HouseholdImport implements WithMultipleSheets
{
    private HouseholdsSheet $householdsSheet;
    private array $dependentSheets = [];

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $wardId = null,
    ) {
        $this->householdsSheet = new HouseholdsSheet($enumeratorId, $wardId);

        $this->dependentSheets = [
            'Members'     => new MembersSheet($this->householdsSheet),
            'Housing'     => new HousingSheet($this->householdsSheet),
            'Utilities'   => new UtilitiesSheet($this->householdsSheet),
            'Economic'    => new EconomicSheet($this->householdsSheet),
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
                $all[] = "[{$name}] {$err}";
            }
        }
        return $all;
    }

    public function getTotalHouseholds(): int
    {
        return $this->householdsSheet->getImportedCount();
    }
}
