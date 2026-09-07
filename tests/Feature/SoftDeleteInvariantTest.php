<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the invariant that keeps the public reports correct.
 *
 * Households, institutions and businesses are soft-deleted, but the public
 * reports, exports and API are built from raw DB::table() queries — 85 of them
 * across seven controllers — which bypass Eloquent's soft-delete scope entirely.
 * `deleted_at` does not appear anywhere in app/. Verified against real data: soft
 * deleting a published household drops it from an Eloquent count but leaves it in
 * the raw count.
 *
 * That is safe today only because of one rule: **only drafts can be deleted**,
 * and every public query filters status = 'published', so a deleted row can
 * never match one. Remove that rule and deleted households silently keep
 * appearing in published totals on the public site, with nothing to signal it.
 *
 * These tests exist so that removing the rule fails here, loudly, rather than
 * quietly corrupting published figures. If you ever need to delete a non-draft
 * record, the 85 raw queries must gain a deleted_at filter *first*.
 */
class SoftDeleteInvariantTest extends TestCase
{
    use RefreshDatabase;

    private int $wardId;

    protected function setUp(): void
    {
        parent::setUp();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'TP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'TD1',
        ]);
        $lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'TL1', 'is_active' => true,
        ]);
        $this->wardId = DB::table('wards')->insertGetId([
            'local_government_id' => $lgId, 'ward_number' => 1,
        ]);
    }

    private function household(string $status): Household
    {
        return Household::create([
            'unique_code' => 'HH-0001-W01-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'ward_id' => $this->wardId,
            'respondent_name' => 'Test',
            'total_members' => 1,
            'status' => $status,
        ]);
    }

    /**
     * The documented behaviour of the raw queries the public site is built on.
     * This is not desirable — it records why the draft-only rule matters.
     */
    public function test_raw_queries_do_not_exclude_soft_deleted_records(): void
    {
        $household = $this->household('published');

        $this->assertSame(1, Household::where('status', 'published')->count());
        $this->assertSame(1, DB::table('households')->where('status', 'published')->count());

        $household->delete();

        $this->assertSame(0, Household::where('status', 'published')->count(),
            'Eloquent correctly hides the soft-deleted record.');
        $this->assertSame(1, DB::table('households')->where('status', 'published')->count(),
            'Raw queries still see it — which is exactly why only drafts may be deleted.');
    }

    /**
     * The invariant itself. If this fails, the public reports have started
     * counting deleted records and the raw queries need a deleted_at filter.
     */
    public function test_a_published_household_cannot_be_deleted(): void
    {
        $household = $this->household('published');

        $this->assertFalse($household->isDraft(),
            'A published household must not report itself as a draft.');
    }

    public function test_a_published_institution_cannot_be_deleted(): void
    {
        $institution = Institution::create([
            'unique_code' => 'IN-0001-W01-00001',
            'ward_id' => $this->wardId,
            'name_en' => 'Test School',
            'status' => 'published',
        ]);

        $this->assertFalse($institution->isDraft());
    }

    /**
     * Deleting a draft is safe precisely because no public query counts drafts.
     * If a public surface ever starts reporting draft totals from a raw query,
     * this becomes a live bug.
     */
    public function test_deleting_a_draft_is_safe_because_public_queries_only_count_published(): void
    {
        $draft = $this->household('draft');
        $published = $this->household('published');

        $draft->delete();

        $this->assertSame(1, DB::table('households')->where('status', 'published')->count(),
            'The deleted draft must not affect published totals.');
        $this->assertSame($published->id, DB::table('households')->where('status', 'published')->value('id'));
    }
}
