<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Who can reach the one-file updater, and how.
 *
 * The page installs code, so it is gated three ways: super_admin only, the
 * password must be re-entered (Laravel's password.confirm — "the super admin is
 * prompted to log in for the update"), and only a package signed with the
 * developer's key is accepted. These tests cover the first two through real
 * HTTP; UpdatePackageTest covers the signature.
 */
class SystemUpdateAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $lgId;
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the updater's state out of the real storage directory.
        $this->workDir = sys_get_temp_dir() . '/apprmp-upd-http-' . bin2hex(random_bytes(4));
        config(['updates.work_dir' => $this->workDir]);

        $provinceId = DB::table('provinces')->insertGetId(['name_np' => 'प', 'name_en' => 'P', 'code' => 'SU1']);
        $districtId = DB::table('districts')->insertGetId(['province_id' => $provinceId, 'name_np' => 'ज', 'name_en' => 'D', 'code' => 'SU2']);
        $this->lgId = DB::table('local_governments')->insertGetId([
            'district_id' => $districtId, 'name_np' => 'ग', 'name_en' => 'LG',
            'type' => 'rural_municipality', 'code' => 'SU3', 'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(glob($this->workDir . '/{,*/,*/*/}*', GLOB_BRACE) ?: []) as $p) {
            is_dir($p) ? @rmdir($p) : @unlink($p);
        }
        @rmdir($this->workDir);
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => uniqid('u', true) . '@example.test',
            'password' => bcrypt('correct-horse'), 'role' => $role,
            'local_government_id' => $this->lgId, 'is_active' => true,
        ]);
    }

    /** Licensing and 2FA are separate gates that answer before this one. */
    private function as(User $user, bool $confirmed = true)
    {
        $req = $this->actingAs($user)->withoutMiddleware([
            \App\Http\Middleware\CheckLicense::class,
            \App\Http\Middleware\RequireTwoFactor::class,
        ]);

        return $confirmed ? $req->withSession(['auth.password_confirmed_at' => time()]) : $req;
    }

    public function test_a_municipal_admin_cannot_open_the_update_page(): void
    {
        $this->as($this->user('municipal_admin'))->get('/admin/system-update')->assertForbidden();
    }

    public function test_a_ward_admin_cannot_reach_it_at_all(): void
    {
        $this->as($this->user('ward_admin'))->get('/admin/system-update')->assertStatus(403);
    }

    /** "Super admin is prompted to log in for the update." */
    public function test_a_super_admin_must_re_enter_their_password_first(): void
    {
        $this->as($this->user('super_admin'), confirmed: false)
            ->get('/admin/system-update')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_a_confirmed_super_admin_sees_the_update_page(): void
    {
        $response = $this->as($this->user('super_admin'))->get('/admin/system-update');

        $response->assertOk();
        $props = $response->getOriginalContent()->getData()['page']['props'];
        $this->assertSame('Admin/SystemUpdate', $response->getOriginalContent()->getData()['page']['component']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $props['currentVersion']);
        $this->assertTrue($props['keyConfigured'], 'the trusted public key ships in config/updates.php');
    }

    /** The step endpoints answer 423 (not an HTML redirect) when the confirmation has lapsed. */
    public function test_steps_require_a_fresh_password_confirmation(): void
    {
        $this->as($this->user('super_admin'), confirmed: false)
            ->postJson('/admin/system-update/step/prepare')
            ->assertStatus(423);
    }

    public function test_an_unknown_step_name_does_not_route(): void
    {
        $this->as($this->user('super_admin'))
            ->postJson('/admin/system-update/step/rm-rf')
            ->assertNotFound();
    }

    public function test_a_file_that_is_not_an_update_package_is_refused_with_a_reason(): void
    {
        $zipPath = sys_get_temp_dir() . '/not-a-package-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('app/Thing.php', '<?php');
        $zip->close();

        $response = $this->as($this->user('super_admin'))->post('/admin/system-update/upload', [
            'package' => new UploadedFile($zipPath, 'deploy.zip', 'application/zip', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertStringContainsString('full deploy ZIP cannot be installed', $response->json('message'));

        @unlink($zipPath);
    }
}
