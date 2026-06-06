<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Ward;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class HouseholdService
{
    public function __construct(private UniqueCodeService $codeService) {}

    public function create(array $data, User $enumerator): Household
    {
        return DB::transaction(function () use ($data, $enumerator) {
            $ward = Ward::findOrFail($data['ward_id']);

            $household = Household::create([
                'unique_code'        => $this->codeService->generateHouseholdCode($ward),
                'ward_id'            => $data['ward_id'],
                'settlement_id'      => $data['settlement_id'] ?: null,
                'enumerator_id'      => $enumerator->id,
                'respondent_name'    => $data['respondent_name'],
                'respondent_contact' => $data['respondent_contact'] ?: null,
                'total_members'      => $data['total_members'] ?? 1,
                'gps_lat'            => $data['gps_lat'] ?: null,
                'gps_lng'            => $data['gps_lng'] ?: null,
                'gps_altitude'       => $data['gps_altitude'] ?: null,
                'gps_accuracy'       => $data['gps_accuracy'] ?: null,
                'photo_number'       => $data['photo_number'] ?: null,
                'survey_date'        => $data['survey_date'] ?? now()->toDateString(),
                'remarks'            => $data['remarks'] ?: null,
                'survey_data'        => $data['survey_data'] ?? null,
                'status'             => 'draft',
            ]);

            $this->syncSubTables($household, $data);

            return $household;
        });
    }

    public function update(Household $household, array $data): Household
    {
        return DB::transaction(function () use ($household, $data) {
            $household->update([
                'sync_locked'        => true,
                'settlement_id'      => array_key_exists('settlement_id', $data)  ? ($data['settlement_id'] ?: null)          : $household->settlement_id,
                'respondent_name'    => $data['respondent_name']    ?? $household->respondent_name,
                'respondent_contact' => $data['respondent_contact'] ?: null,
                'total_members'      => $data['total_members']      ?? $household->total_members,
                'gps_lat'            => $data['gps_lat']            ?: $household->gps_lat,
                'gps_lng'            => $data['gps_lng']            ?: $household->gps_lng,
                'gps_altitude'       => $data['gps_altitude']       ?: null,
                'gps_accuracy'       => $data['gps_accuracy']       ?: null,
                'photo_number'       => trim($data['photo_number'] ?? $household->photo_number ?? '') ?: null,
                'survey_date'        => $data['survey_date']        ?? $household->survey_date,
                'remarks'            => $data['remarks']            ?: null,
                'survey_data'        => $data['survey_data']        ?? $household->survey_data,
            ]);

            $this->syncSubTables($household, $data);

            return $household->fresh();
        });
    }

    private function syncSubTables(Household $household, array $data): void
    {
        if (array_key_exists('housing', $data))     $this->syncHousing($household, $data['housing']);
        if (array_key_exists('utilities', $data))   $this->syncUtilities($household, $data['utilities']);
        if (array_key_exists('economic', $data))    $this->syncEconomic($household, $data['economic']);
        if (array_key_exists('agriculture', $data)) $this->syncAgriculture($household, $data['agriculture']);
        if (array_key_exists('disaster', $data))    $this->syncDisaster($household, $data['disaster']);
        if (array_key_exists('livestock', $data))   $this->syncLivestock($household, $data['livestock']);
        if (array_key_exists('members', $data))     $this->syncMembers($household, $data['members']);
    }

    private function syncHousing(Household $household, array $data): void
    {
        $data = $this->nullifyEmpty($data);
        if (isset($data['risk_types']) && is_array($data['risk_types'])) {
            $data['risk_types'] = array_values(array_filter($data['risk_types'])) ?: null;
        }
        $household->housing()->updateOrCreate(['household_id' => $household->id], $data);
    }

    private function syncUtilities(Household $household, array $data): void
    {
        $household->utilities()->updateOrCreate(['household_id' => $household->id], $this->nullifyEmpty($data));
    }

    private function syncEconomic(Household $household, array $data): void
    {
        $data = $this->nullifyEmpty($data);
        if (isset($data['loan_sources']) && is_array($data['loan_sources'])) {
            $data['loan_sources'] = array_values(array_filter($data['loan_sources'])) ?: null;
        }
        $household->economic()->updateOrCreate(['household_id' => $household->id], $data);
    }

    private function syncAgriculture(Household $household, array $data): void
    {
        $data = $this->nullifyEmpty($data);
        foreach (['agri_categories', 'chem_fertilizer_types', 'fertilizer_sources', 'chem_pesticide_types', 'Farm_Challenges'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_values(array_filter($data[$field])) ?: null;
            }
        }
        $household->agriculture()->updateOrCreate(['household_id' => $household->id], $data);
    }

    private function syncDisaster(Household $household, array $data): void
    {
        $data = $this->nullifyEmpty($data);
        foreach (['risk_types', 'past_events', 'death_rows', 'human_loss_rows', 'physical_loss_rows', 'relief_rows'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = array_values(array_filter($data[$field])) ?: null;
            }
        }
        $household->disaster()->updateOrCreate(['household_id' => $household->id], $data);
    }

    private function syncLivestock(Household $household, array $items): void
    {
        $incoming   = collect($items)->filter(fn($i) => !empty($i['livestock_type']));
        $keepTypes  = $incoming->pluck('livestock_type')->all();

        // Delete rows removed from the form
        $household->livestock()
            ->when(!empty($keepTypes), fn($q) => $q->whereNotIn('livestock_type', $keepTypes))
            ->delete();

        // Update existing / insert new rows by livestock_type
        foreach ($incoming as $item) {
            $household->livestock()->updateOrCreate(
                ['livestock_type' => $item['livestock_type']],
                $this->nullifyEmpty(Arr::except($item, ['livestock_type']))
            );
        }
    }

    private function syncMembers(Household $household, array $members): void
    {
        $existingIds = $household->members()->pluck('id')->toArray();
        $processedIds = [];

        foreach ($members as $idx => $memberData) {
            $memberData = $this->nullifyEmpty($memberData);

            // Skip placeholder rows with no name
            if (empty($memberData['full_name'])) {
                continue;
            }

            // Boolean columns must not be null
            $booleanDefaults = [
                'is_household_head'    => false,
                'is_literate'          => false,
                'disability_status'    => false,
                'chronic_illness'      => false,
                'has_birth_certificate'=> false,
                'has_citizenship'      => false,
                'has_voters_card'      => false,
                'has_national_id_card' => false,
                'has_passport'         => false,
            ];
            foreach ($booleanDefaults as $col => $default) {
                if (!isset($memberData[$col]) || $memberData[$col] === null) {
                    $memberData[$col] = $default;
                }
            }

            $memberData['sort_order'] = $idx;
            $memberId = $memberData['_id'] ?? null;
            unset($memberData['_id'], $memberData['created_at'], $memberData['updated_at']);

            // JSON array columns must be encoded for QueryBuilder mass-update (bypasses Eloquent casts)
            foreach ([
                'chronic_illness_types', 'childvaccine', 'cause_not_school',
                'drivers_license_category', 'smoking_drinking_items', 'residing_country',
            ] as $jsonCol) {
                if (isset($memberData[$jsonCol]) && is_array($memberData[$jsonCol])) {
                    $memberData[$jsonCol] = json_encode(array_values(array_filter($memberData[$jsonCol]))) ?: null;
                }
            }

            if ($memberId && in_array($memberId, $existingIds)) {
                $household->members()->where('id', $memberId)->update($memberData);
                $processedIds[] = $memberId;
            } else {
                $member = $household->members()->create($memberData);
                $processedIds[] = $member->id;
            }
        }

        $toDelete = array_diff($existingIds, $processedIds);
        if ($toDelete) {
            $household->members()->whereIn('id', array_values($toDelete))->delete();
        }
    }

    private function nullifyEmpty(array $data): array
    {
        return array_map(fn($v) => ($v === '' ? null : $v), $data);
    }
}
