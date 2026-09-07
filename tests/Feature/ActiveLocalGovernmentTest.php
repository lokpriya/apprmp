<?php

namespace Tests\Feature;

use App\Models\LocalGovernment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression tests for resolving "the active local government".
 *
 * This single value decides which municipality the entire public site reports.
 * It used to be resolved in seven places with `where('is_active', true)->first()
 * ?? first()`, and `first()` on an unordered query returns whatever row the
 * storage engine yields — so the identity of every report and export depended on
 * physical row order.
 *
 * That fallback is not hypothetical. local_governments carries all 753 Nepal
 * municipalities as master data, and on the rong database none of them is
 * flagged active, so the fallback is the path actually taken in production.
 */
class ActiveLocalGovernmentTest extends TestCase
{
    use RefreshDatabase;

    private int $districtId;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'प', 'name_en' => 'P', 'code' => 'TP1',
        ]);
        $this->districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'TD1',
        ]);
    }

    private function lg(int $id, string $name, bool $active): void
    {
        DB::table('local_governments')->insert([
            'id' => $id,
            'district_id' => $this->districtId,
            'name_np' => $name,
            'name_en' => $name,
            'type' => 'rural_municipality',
            'code' => 'C'.$id,
            'is_active' => $active,
        ]);
    }

    public function test_an_active_local_government_wins_over_a_lower_id_inactive_one(): void
    {
        $this->lg(1, 'Inactive First', false);
        $this->lg(2, 'The Active One', true);

        $this->assertSame('The Active One', LocalGovernment::getFirstCached()->name_en);
        $this->assertSame('The Active One', LocalGovernment::getActiveFull()->name_en);
    }

    /**
     * The production case: 753 master-data rows, none active. The result must
     * still be stable rather than storage-order dependent.
     */
    public function test_with_none_active_the_lowest_id_is_chosen_deterministically(): void
    {
        $this->lg(3, 'Third', false);
        $this->lg(1, 'First', false);
        $this->lg(2, 'Second', false);

        $this->assertSame('First', LocalGovernment::getFirstCached()->name_en);

        Cache::flush();
        $this->assertSame('First', LocalGovernment::getActiveFull()->name_en, 'Repeat lookups must agree.');
    }

    /** Among several active rows the choice must also be stable. */
    public function test_the_lowest_id_active_row_is_chosen(): void
    {
        $this->lg(1, 'Inactive', false);
        $this->lg(5, 'Active Five', true);
        $this->lg(3, 'Active Three', true);

        $this->assertSame('Active Three', LocalGovernment::getFirstCached()->name_en);
    }

    /** The two accessors must never disagree — they did when duplicated. */
    public function test_both_accessors_resolve_to_the_same_local_government(): void
    {
        $this->lg(1, 'One', false);
        $this->lg(2, 'Two', true);

        $this->assertSame(
            LocalGovernment::getFirstCached()->id,
            LocalGovernment::getActiveFull()->id
        );
    }

    public function test_get_active_full_eager_loads_district_and_province(): void
    {
        $this->lg(1, 'Only', true);

        $lg = LocalGovernment::getActiveFull();

        $this->assertTrue($lg->relationLoaded('district'), 'district must be eager loaded');
        $this->assertTrue($lg->relationLoaded('province') || $lg->district->relationLoaded('province'),
            'province must be eager loaded through district');
    }

    /** Saving an LG busts the caches; a stale entry is how this went unnoticed. */
    public function test_saving_a_local_government_busts_both_caches(): void
    {
        $this->lg(1, 'Before', true);

        $this->assertSame('Before', LocalGovernment::getFirstCached()->name_en);
        $this->assertSame('Before', LocalGovernment::getActiveFull()->name_en);

        LocalGovernment::find(1)->update(['name_en' => 'After']);

        $this->assertSame('After', LocalGovernment::getFirstCached()->name_en);
        $this->assertSame('After', LocalGovernment::getActiveFull()->name_en);
    }

    public function test_it_returns_null_when_no_local_government_exists(): void
    {
        $this->assertNull(LocalGovernment::getFirstCached());
        $this->assertNull(LocalGovernment::getActiveFull());
    }
}
