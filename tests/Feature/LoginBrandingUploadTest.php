<?php

namespace Tests\Feature;

use App\Models\LocalGovernment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for the login-page landmark photo (ल्यान्डमार्क फोटो).
 *
 * The reported symptom was "it does not upload and does not save", with no error
 * shown. The controller was correct; the file never reached PHP, because
 * upload_max_filesize was 2M while the rule (and the on-screen label) promised
 * 4 MB. PHP rejects an oversized upload before Laravel runs, so the response
 * carried nothing to display and the card simply stopped spinning.
 *
 * These tests cover the parts that live in the app. The environment half —
 * upload_max_filesize >= the largest validation rule — is asserted separately in
 * UploadLimitsTest, because a phpunit request never passes through the SAPI
 * limit and so cannot reproduce it.
 */
class LoginBrandingUploadTest extends TestCase
{
    use RefreshDatabase;

    private int $lgId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $provinceId = DB::table('provinces')->insertGetId([
            'name_np' => 'परीक्षण प्रदेश', 'name_en' => 'Test Province', 'code' => 'TP1',
        ]);
        $districtId = DB::table('districts')->insertGetId([
            'province_id' => $provinceId,
            'name_np' => 'परीक्षण जिल्ला', 'name_en' => 'Test District', 'code' => 'TD1',
        ]);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId,
            'name_np' => 'परीक्षण गाउँपालिका', 'name_en' => 'Test RM',
            'type' => 'rural_municipality', 'code' => 'TL1', 'is_active' => true,
        ]);
    }

    /**
     * Admin roles are forced through 2FA, so an admin without a confirmed second
     * factor is redirected to /two-factor/setup and never reaches the controller
     * under test — which silently turns any assertion below into a false pass.
     */
    private function admin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'local_government_id' => $this->lgId,
            'two_factor_secret' => encrypt('TESTSECRET'),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    private function landmarkPath(): ?string
    {
        $config = json_decode(
            (string) DB::table('local_governments')->where('id', $this->lgId)->value('welcome_config'),
            true
        );

        return $config['login_branding']['landmark'] ?? null;
    }

    public function test_uploading_a_landmark_stores_the_file_and_saves_the_path(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/login-landmark', [
                'landmark' => UploadedFile::fake()->image('landmark.png', 1200, 600),
            ])
            ->assertRedirect(route('admin.settings.index', ['tab' => 'welcome']))
            ->assertSessionHasNoErrors();

        $path = $this->landmarkPath();

        $this->assertNotNull($path, 'The landmark path was not written to welcome_config.');
        $this->assertStringStartsWith('lg-profile/', $path);
        Storage::disk('public')->assertExists($path);
    }

    /**
     * The controller must reject before the SAPI would, so the user sees a
     * validation message instead of a request that vanishes.
     */
    public function test_an_oversized_image_is_rejected_with_an_error(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/login-landmark', [
                'landmark' => UploadedFile::fake()->image('huge.png')->size(5 * 1024),
            ])
            ->assertSessionHasErrors('landmark');

        $this->assertNull($this->landmarkPath());
    }

    public function test_a_non_image_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings/login-landmark', [
                'landmark' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('landmark');

        $this->assertNull($this->landmarkPath());
    }

    /**
     * updateLoginBranding() writes the same login_branding array. It must merge,
     * not replace — otherwise saving the headline silently drops the photo.
     */
    public function test_saving_the_branding_text_preserves_the_landmark(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/login-landmark', [
            'landmark' => UploadedFile::fake()->image('landmark.png', 1200, 600),
        ])->assertSessionHasNoErrors();

        $path = $this->landmarkPath();
        $this->assertNotNull($path);

        $this->actingAs($admin)->post('/admin/settings/login-branding', [
            'headline_line1' => 'Data is potential ENERGY,',
            'headline_line2' => 'not instant value',
            'highlight_word' => 'ENERGY',
        ])->assertSessionHasNoErrors();

        $this->assertSame($path, $this->landmarkPath(), 'Saving branding text wiped the landmark photo.');
    }

    public function test_deleting_the_landmark_removes_the_file_and_the_path(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/login-landmark', [
            'landmark' => UploadedFile::fake()->image('landmark.png', 1200, 600),
        ])->assertSessionHasNoErrors();

        $path = $this->landmarkPath();
        $this->assertNotNull($path);

        $this->actingAs($admin)->delete('/admin/settings/login-landmark')
            ->assertSessionHasNoErrors();

        $this->assertNull($this->landmarkPath());
        Storage::disk('public')->assertMissing($path);
    }

    /** Replacing the photo must not leave the previous file behind on disk. */
    public function test_replacing_the_landmark_deletes_the_previous_file(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/settings/login-landmark', [
            'landmark' => UploadedFile::fake()->image('first.png', 1200, 600),
        ])->assertSessionHasNoErrors();
        $first = $this->landmarkPath();

        $this->actingAs($admin)->post('/admin/settings/login-landmark', [
            'landmark' => UploadedFile::fake()->image('second.png', 1200, 600),
        ])->assertSessionHasNoErrors();
        $second = $this->landmarkPath();

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    /** A locked settings page must refuse the upload rather than accept it quietly. */
    public function test_upload_is_refused_while_settings_are_locked(): void
    {
        LocalGovernment::where('id', $this->lgId)->update(['settings_locked_at' => now()]);

        $this->actingAs($this->admin())
            ->post('/admin/settings/login-landmark', [
                'landmark' => UploadedFile::fake()->image('landmark.png', 1200, 600),
            ])
            ->assertForbidden();

        $this->assertNull($this->landmarkPath());
    }
}
