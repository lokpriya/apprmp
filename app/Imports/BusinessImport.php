<?php

namespace App\Imports;

use App\Imports\Sheets\BusinessesSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BusinessImport implements WithMultipleSheets
{
    private BusinessesSheet $businessesSheet;

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $wardId = null,
    ) {
        $this->businessesSheet = new BusinessesSheet($enumeratorId, $wardId);
    }

    public function sheets(): array
    {
        return [
            'Businesses' => $this->businessesSheet,
        ];
    }

    public function getErrors(): array
    {
        return $this->businessesSheet->getErrors();
    }

    public function getTotalBusinesses(): int
    {
        return $this->businessesSheet->getImportedCount();
    }
}
