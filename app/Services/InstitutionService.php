<?php

namespace App\Services;

use App\Models\Institution;
use App\Models\Ward;
use App\Models\User;
use App\Support\RetriesUniqueCode;
use Illuminate\Support\Facades\DB;

class InstitutionService
{
    use RetriesUniqueCode;

    public function __construct(private UniqueCodeService $codeService) {}

    public function create(array $data, User $enumerator): Institution
    {
        // See RetriesUniqueCode: concurrent saves in one ward derive the same
        // unique_code from an unlocked read.
        return $this->retryOnDuplicateCode(fn () => DB::transaction(function () use ($data, $enumerator) {
            $ward = Ward::findOrFail($data['ward_id']);

            $institution = Institution::create([
                'unique_code'         => $this->codeService->generateInstitutionCode($ward),
                'ward_id'             => $data['ward_id'],
                'settlement_id'       => $data['settlement_id'] ?: null,
                'institution_type'    => $data['institution_type'] ?: null,
                'name_np'             => $data['name_np'] ?: null,
                'name_en'             => $data['name_en'],
                'established_date'    => $data['established_date'] ?: null,
                'ownership_type'      => $data['ownership_type'] ?: null,
                'registration_number' => $data['registration_number'] ?: null,
                'enumerator_id'       => $enumerator->id,
                'gps_lat'             => $data['gps_lat'] ?: null,
                'gps_lng'             => $data['gps_lng'] ?: null,
                'gps_altitude'        => $data['gps_altitude'] ?: null,
                'gps_accuracy'        => $data['gps_accuracy'] ?: null,
                'photo_number'        => $data['photo_number'] ?: null,
                'survey_date'         => $data['survey_date'] ?: null,
                'respondent_name'     => $data['respondent_name'] ?: null,
                'respondent_contact'  => $data['respondent_contact'] ?: null,
                'remarks'             => $data['remarks'] ?: null,
                'survey_data'         => $data['survey_data'] ?? null,
                'status'              => 'draft',
            ]);

            $this->syncSubTables($institution, $data);

            return $institution;
        }));
    }

    public function update(Institution $institution, array $data): Institution
    {
        return DB::transaction(function () use ($institution, $data) {
            $institution->update([
                'sync_locked'         => true,
                'settlement_id'       => $data['settlement_id'] ?: null,
                'institution_type'    => $data['institution_type'] ?: $institution->institution_type,
                'name_np'             => $data['name_np'] ?: null,
                'name_en'             => $data['name_en'] ?? $institution->name_en,
                'established_date'    => $data['established_date'] ?: null,
                'ownership_type'      => $data['ownership_type'] ?: null,
                'registration_number' => $data['registration_number'] ?: null,
                'gps_lat'             => $data['gps_lat'] ?: $institution->gps_lat,
                'gps_lng'             => $data['gps_lng'] ?: $institution->gps_lng,
                'gps_altitude'        => $data['gps_altitude'] ?: $institution->gps_altitude,
                'gps_accuracy'        => $data['gps_accuracy'] ?: $institution->gps_accuracy,
                'photo_number'        => array_key_exists('photo_number', $data) ? ($data['photo_number'] ?: null) : $institution->photo_number,
                'survey_date'         => $data['survey_date'] ?: null,
                'respondent_name'     => $data['respondent_name'] ?: null,
                'respondent_contact'  => $data['respondent_contact'] ?: null,
                'remarks'             => $data['remarks'] ?: null,
                'survey_data'         => $data['survey_data'] ?? null,
            ]);

            $this->syncSubTables($institution, $data);

            return $institution->fresh();
        });
    }

    private function syncSubTables(Institution $institution, array $data): void
    {
        if (array_key_exists('infrastructure', $data)) {
            $institution->infrastructure()->updateOrCreate(
                ['institution_id' => $institution->id],
                $this->nullifyEmpty($data['infrastructure'])
            );
        }

        if (array_key_exists('health', $data) && !empty($data['health'])) {
            $institution->healthDetail()->updateOrCreate(
                ['institution_id' => $institution->id],
                $this->nullifyEmpty($data['health'])
            );
        }

        if (array_key_exists('services', $data)) {
            $institution->services()->delete();
            foreach ($data['services'] as $item) {
                if (!empty($item['service_name'])) {
                    $institution->services()->create($this->nullifyEmpty($item));
                }
            }
        }

        if (array_key_exists('staff', $data)) {
            $institution->staff()->delete();
            foreach ($data['staff'] as $item) {
                if (!empty($item['staff_category'])) {
                    $institution->staff()->create($this->nullifyEmpty($item));
                }
            }
        }

        if (array_key_exists('education', $data)) {
            $institution->education()->delete();
            foreach ($data['education'] as $item) {
                if (!empty($item['grade'])) {
                    $institution->education()->create($this->nullifyEmpty($item));
                }
            }
        }
    }

    private function nullifyEmpty(array $data): array
    {
        return array_map(fn($v) => ($v === '' ? null : $v), $data);
    }
}
