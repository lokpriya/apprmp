<?php

namespace App\Imports;

use App\Imports\Sheets\InstitutionsSheet;
use App\Imports\Sheets\InfrastructureSheet;
use App\Imports\Sheets\StaffSheet;
use App\Imports\Sheets\InstEducationSheet;
use App\Imports\Sheets\InstHealthSheet;
use App\Imports\Sheets\InstServicesSheet;
use App\Imports\Sheets\TeachersSheet;
use App\Imports\Sheets\TypeSpecificSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class InstitutionImport implements WithMultipleSheets
{
    private InstitutionsSheet $institutionsSheet;
    private array $dependentSheets = [];

    public function __construct(
        private readonly int  $enumeratorId,
        private readonly ?int $wardId = null,
    ) {
        $this->institutionsSheet = new InstitutionsSheet($enumeratorId, $wardId);

        $this->dependentSheets = [
            'Infrastructure' => new InfrastructureSheet($this->institutionsSheet),
            'Staff'          => new StaffSheet($this->institutionsSheet),
            'Education'      => new InstEducationSheet($this->institutionsSheet),
            'Health'         => new InstHealthSheet($this->institutionsSheet),
            'Services'       => new InstServicesSheet($this->institutionsSheet),
            'Teachers'       => new TeachersSheet($this->institutionsSheet),
            'TypeSpecific'   => new TypeSpecificSheet($this->institutionsSheet),
        ];
    }

    public function sheets(): array
    {
        return array_merge(
            ['Institutions' => $this->institutionsSheet],
            $this->dependentSheets
        );
    }

    public function getErrors(): array
    {
        $all = [];
        $allSheets = array_merge(['Institutions' => $this->institutionsSheet], $this->dependentSheets);
        foreach ($allSheets as $name => $sheet) {
            foreach ($sheet->getErrors() as $err) {
                $all[] = "[{$name}] {$err}";
            }
        }
        return $all;
    }

    public function getTotalInstitutions(): int
    {
        return $this->institutionsSheet->getImportedCount();
    }
}
