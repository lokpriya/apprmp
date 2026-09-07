<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HouseholdController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PublishQueueController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SurveyOptionsController;
use App\Http\Controllers\Admin\RolePermissionsController;
use App\Http\Controllers\Admin\ProgressController;
use App\Http\Controllers\Admin\DataExportController;
use App\Http\Controllers\Admin\GoogleSyncController;
use App\Http\Controllers\Admin\SurveyRoundController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\Admin\LicenseController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\EditRequestController;
use App\Http\Controllers\FieldVerificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\Public\AnalyticsController;
use App\Http\Controllers\Public\BusinessReportController;
use App\Http\Controllers\Public\InstitutionReportController;
use App\Http\Controllers\Public\ReportController;
use App\Http\Controllers\Public\ReportExportController;
use App\Http\Controllers\Public\OpenDataController;
use App\Http\Controllers\Public\FeedbackController as PublicFeedbackController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Api\PublicDataController;
use Illuminate\Support\Facades\Route;

// ── Operational health probe ─────────────────────────────────────────────────
// Intentionally outside the maintenance/license/public-access gates so an uptime
// monitor can tell a dead server apart from a deliberately gated one.
Route::get('/healthz', \App\Http\Controllers\HealthController::class)
    ->middleware('throttle:60,1')
    ->name('healthz');

// ── Public REST API (aggregate published data, no auth) ──────────────────────
Route::prefix('api/v1')->middleware(['throttle:60,1'])->group(function () {
    Route::get('/summary',      [PublicDataController::class, 'summary'])     ->name('api.v1.summary');
    Route::get('/population',   [PublicDataController::class, 'population'])  ->name('api.v1.population');
    Route::get('/wards',        [PublicDataController::class, 'wards'])       ->name('api.v1.wards');
    Route::get('/institutions', [PublicDataController::class, 'institutions'])->name('api.v1.institutions');
    Route::get('/economics',    [PublicDataController::class, 'economics'])   ->name('api.v1.economics');
});

// ── Public access captcha gate ───────────────────────────────────────────────
Route::middleware('maintenance')->group(function () {
    Route::get('/verify', function () {
        $a = rand(1, 12);
        $b = rand(1, 12);
        $op = ['+', '-', '×'][array_rand(['+', '-', '×'])];
        if ($op === '-' && $a < $b) [$a, $b] = [$b, $a];
        $answer = match($op) { '+' => $a + $b, '-' => $a - $b, '×' => $a * $b };
        session(['captcha_answer' => $answer]);
        $lg = \App\Models\LocalGovernment::getFirstCached();
        return view('public.verify', ['problem' => "$a $op $b", 'lg' => $lg]);
    })->name('public.verify');

    Route::post('/verify', function (\Illuminate\Http\Request $request) {
        $request->validate(['answer' => 'required|integer']);
        if ((int) $request->answer === (int) session('captcha_answer')) {
            session(['public_verified' => true]);
            session()->forget('captcha_answer');
            return redirect(session()->pull('public_intended', '/public'));
        }
        return back()->withErrors(['answer' => 'उत्तर गलत छ। पुनः प्रयास गर्नुहोस्।']);
    })->name('public.verify.post');
});

// Public analytics portal (no auth required)
// Throttled: every report below runs several aggregations over the full member
// table with no authentication. On shared hosting the entry-process cap is the
// binding constraint, so an unthrottled crawler can exhaust the account and take
// down every site on it. 120/min is far above real browsing.
Route::prefix('public')->name('public.')->middleware(['maintenance', 'public.access', 'throttle:120,1'])->group(function () {
    Route::get('/', [AnalyticsController::class, 'index'])->name('dashboard');
    Route::get('/profile',         [AnalyticsController::class, 'profile'])->name('profile');
    Route::get('/representatives', [AnalyticsController::class, 'representatives'])->name('representatives');
    Route::get('/employees',       [AnalyticsController::class, 'employees'])->name('employees');
    Route::get('/gallery',         [AnalyticsController::class, 'gallery'])->name('gallery');
    Route::get('/downloads',       [AnalyticsController::class, 'downloads'])->name('downloads');
    Route::get('/feedback',        [PublicFeedbackController::class, 'create'])->name('feedback');
    Route::post('/feedback',       [PublicFeedbackController::class, 'store'])->name('feedback.store')->middleware('throttle:5,1');
    Route::get('/housing', [AnalyticsController::class, 'housing'])->name('housing');
    Route::get('/map',             [AnalyticsController::class, 'map'])->name('map');
    Route::get('/institution-map', [AnalyticsController::class, 'institutionMap'])->name('institution-map');
    Route::get('/ward/{ward}', [AnalyticsController::class, 'ward'])->name('ward');
    Route::get('/data/population', [AnalyticsController::class, 'population'])->name('data.population');
    Route::get('/data/institutions', [AnalyticsController::class, 'institutions'])->name('data.institutions');

    // ── Public Cross-Tab Reports ─────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',                    [ReportController::class,    'index'])          ->name('index');
        Route::get('/demographics',        [ReportController::class,    'demographics'])   ->name('demographics');
        Route::get('/age-groups',          [ReportController::class,    'ageGroups'])      ->name('age-groups');
        Route::get('/education',           [ReportController::class,    'education'])      ->name('education');
        Route::get('/health',              [ReportController::class,    'health'])         ->name('health');
        Route::get('/employment',          [ReportController::class,    'employment'])     ->name('employment');
        Route::get('/housing',             [ReportController::class,    'housing'])        ->name('housing');
        Route::get('/utilities',           [ReportController::class,    'utilities'])      ->name('utilities');
        Route::get('/economics',           [ReportController::class,    'economics'])      ->name('economics');
        Route::get('/agriculture',         [ReportController::class,    'agriculture'])    ->name('agriculture');
        Route::get('/migration',           [ReportController::class,    'migration'])      ->name('migration');
        Route::get('/child-development',   [ReportController::class,    'childDevelopment'])->name('child-development');
        Route::get('/documents',           [ReportController::class,    'documents'])      ->name('documents');
        Route::get('/disaster',            [ReportController::class,    'disaster'])            ->name('disaster');
        Route::get('/women-empowerment',   [ReportController::class,    'womenEmpowerment'])  ->name('women-empowerment');
        Route::get('/digital-connectivity',[ReportController::class,    'digitalConnectivity'])->name('digital-connectivity');
        Route::get('/social-security',     [ReportController::class,    'socialSecurity'])    ->name('social-security');
        Route::get('/livestock-detail',    [ReportController::class,    'livestockDetail'])   ->name('livestock-detail');
        Route::get('/land-agriculture',    [ReportController::class,    'landAgriculture'])   ->name('land-agriculture');
        Route::get('/development-index',   [ReportController::class,    'developmentIndex'])  ->name('development-index');
        Route::get('/crop-production',     [ReportController::class,    'cropProduction'])     ->name('crop-production');
        Route::get('/health-awareness',    [ReportController::class,    'healthAwareness'])    ->name('health-awareness');
        Route::get('/wellbeing',           [ReportController::class,    'wellbeing'])          ->name('wellbeing');
        Route::get('/income-breakdown',    [ReportController::class,    'incomeBreakdown'])    ->name('income-breakdown');
        Route::get('/social-empowerment',  [ReportController::class,    'socialEmpowerment'])  ->name('social-empowerment');
        Route::get('/family-profile',      [ReportController::class,    'familyProfile'])      ->name('family-profile');
        Route::get('/agri-enterprises',    [ReportController::class,    'agriEnterprises'])    ->name('agri-enterprises');
        Route::get('/export/{category}',   [ReportExportController::class, 'download'])       ->name('export');
        Route::get('/population-pyramid',  [AnalyticsController::class, 'populationPyramid'])->name('population-pyramid');
        Route::get('/ward-summary',        [AnalyticsController::class, 'wardSummaryPage'])     ->name('ward-summary');
        Route::get('/ward-profile',        [AnalyticsController::class, 'wardProfileDefault']) ->name('ward-profile');
        Route::get('/ward/{wardId}',       [AnalyticsController::class, 'wardProfile'])        ->name('ward-profile.ward');
        Route::get('/round-comparison',    [ReportController::class,    'roundComparison'])    ->name('round-comparison');
        Route::get('/ward-comparison',     [AnalyticsController::class, 'wardComparisonPage']) ->name('ward-comparison');
    });

    // ── Public Business Reports ──────────────────────────────────────────────
    Route::prefix('business-reports')->name('business-reports.')->group(function () {
        Route::get('/',             [BusinessReportController::class, 'index'])       ->name('index');
        Route::get('/by-type',      [BusinessReportController::class, 'byType'])      ->name('by-type');
        Route::get('/by-field',     [BusinessReportController::class, 'byField'])     ->name('by-field');
        Route::get('/by-location',  [BusinessReportController::class, 'byLocation'])  ->name('by-location');
        Route::get('/registration', [BusinessReportController::class, 'registration'])->name('registration');
    });

    // ── Public Institution Reports ───────────────────────────────────────────
    Route::prefix('institution-reports')->name('institution-reports.')->group(function () {
        Route::get('/',              [InstitutionReportController::class, 'index'])         ->name('index');
        Route::get('/by-type',       [InstitutionReportController::class, 'byType'])        ->name('by-type');
        Route::get('/by-ownership',  [InstitutionReportController::class, 'byOwnership'])   ->name('by-ownership');
        Route::get('/infrastructure',[InstitutionReportController::class, 'infrastructure'])->name('infrastructure');
        Route::get('/services',      [InstitutionReportController::class, 'services'])      ->name('services');
        Route::get('/staff',         [InstitutionReportController::class, 'staff'])         ->name('staff');
        Route::get('/education',     fn() => redirect('/public/institution-reports/school', 301));
        Route::get('/health',        [InstitutionReportController::class, 'health'])        ->name('health');
        Route::get('/school',        [InstitutionReportController::class, 'school'])        ->name('school');
    });

    // ── Open Data CSV Downloads ──────────────────────────────────────────────
    Route::prefix('opendata')->name('opendata.')->group(function () {
        Route::get('/',             [OpenDataController::class, 'index'])        ->name('index');
        Route::get('/wards',        [OpenDataController::class, 'wards'])       ->name('wards');
        Route::get('/institutions', [OpenDataController::class, 'institutions'])->name('institutions');
        Route::get('/businesses',   [OpenDataController::class, 'businesses'])  ->name('businesses');
    });
});

// Auth routes
Route::get('/', function () {
    return redirect()->route('public.dashboard');
});

// ── 2FA challenge (guest — happens before full login) ────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge',  [TwoFactorController::class, 'showChallenge'])  ->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorController::class, 'verifyChallenge'])->name('two-factor.verify');
});

Route::middleware(['auth', 'license', '2fa.require'])->group(function () {

    // Impersonation stop — any authenticated user can exit (ward_reviewer, enumerator, etc.)
    Route::post('/admin/impersonate/stop', [ImpersonateController::class, 'stop'])->name('admin.impersonate.stop');

    // Dashboard — redirects based on role
    Route::get('/dashboard', function () {
        $role = auth()->user()->role;
        return match($role) {
            'enumerator'      => redirect()->route('households.index'),
            'ward_reviewer'   => redirect()->route('review.index'),
            'ward_admin'      => redirect()->route('households.index'),
            'municipal_admin',
            'super_admin'     => redirect()->route('admin.dashboard'),
            default           => view('dashboard'),
        };
    })->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Enumerator + Reviewer Routes ───────────────────────────────────────
    // ward_reviewer included so they can access show/index pages for records they review.
    // Controller-level policies enforce write restrictions per role.
    Route::middleware(['role:enumerator,ward_reviewer,ward_admin,municipal_admin,super_admin', 'ward.scope', 'survey.period'])->group(function () {
        Route::get('households/{household}/preview', [HouseholdController::class, 'preview'])->name('households.preview');
        Route::get('households/{household}/members/{member}', [HouseholdController::class, 'showMember'])->name('households.members.show');
        Route::post('households/bulk-submit',  [HouseholdController::class, 'bulkSubmit']) ->name('households.bulk-submit');
        Route::post('households/bulk-approve', [HouseholdController::class, 'bulkApprove'])->name('households.bulk-approve');
        Route::post('households/bulk-reject',  [HouseholdController::class, 'bulkReject']) ->name('households.bulk-reject');
        Route::resource('households', HouseholdController::class);
        Route::post('households/{household}/submit', [HouseholdController::class, 'submit'])->name('households.submit');
        Route::post('institutions/bulk-submit',  [InstitutionController::class, 'bulkSubmit']) ->name('institutions.bulk-submit');
        Route::post('institutions/bulk-approve', [InstitutionController::class, 'bulkApprove'])->name('institutions.bulk-approve');
        Route::post('institutions/bulk-reject',  [InstitutionController::class, 'bulkReject']) ->name('institutions.bulk-reject');
        Route::resource('institutions', InstitutionController::class);
        Route::post('institutions/{institution}/submit', [InstitutionController::class, 'submit'])->name('institutions.submit');

        // ── Business ──────────────────────────────────────────────────────
        Route::post('businesses/bulk-submit',    [BusinessController::class, 'bulkSubmit'])   ->name('businesses.bulk-submit');
        Route::post('businesses/bulk-approve',   [BusinessController::class, 'bulkApprove'])  ->name('businesses.bulk-approve');
        Route::post('businesses/bulk-reject',    [BusinessController::class, 'bulkReject'])   ->name('businesses.bulk-reject');
        Route::resource('businesses', BusinessController::class);
        Route::post('businesses/{business}/submit', [BusinessController::class, 'submit'])->name('businesses.submit');
        Route::get('api/businesses/households',         [BusinessController::class, 'householdsForWard'])    ->name('businesses.api.households');
        Route::get('api/businesses/members',            [BusinessController::class, 'membersForHousehold']) ->name('businesses.api.members');
        Route::get('api/businesses/ward-settlements',   [BusinessController::class, 'settlementsForWard'])  ->name('businesses.api.ward-settlements');
    });

    // ─── PDF Export ─────────────────────────────────────────────────────────
    Route::middleware(['role:enumerator,ward_reviewer,ward_admin,municipal_admin,super_admin'])->group(function () {
        Route::get('households/{household}/pdf',   [PdfController::class, 'household'])  ->name('households.pdf');
        Route::get('institutions/{institution}/pdf',[PdfController::class, 'institution'])->name('institutions.pdf');
        Route::get('businesses/{business}/pdf',    [PdfController::class, 'business'])   ->name('businesses.pdf');
    });

    // ─── 2FA management (authenticated users) ────────────────────────────────
    Route::get('/two-factor/setup',              [TwoFactorController::class, 'showSetupPage'])  ->name('two-factor.setup-page');
    Route::post('/two-factor/setup',             [TwoFactorController::class, 'setup'])          ->name('two-factor.setup');
    Route::post('/two-factor/confirm',           [TwoFactorController::class, 'confirm'])        ->name('two-factor.confirm');
    Route::post('/two-factor/disable',           [TwoFactorController::class, 'disable'])        ->name('two-factor.disable');
    Route::post('/two-factor/recovery-codes',    [TwoFactorController::class, 'regenerateCodes'])->name('two-factor.recovery-codes');

    // ─── Notifications ──────────────────────────────────────────────────────
    Route::post('notifications/{id}/read',  [NotificationController::class, 'markRead'])   ->name('notifications.read');
    Route::post('notifications/read-all',   [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // ─── Edit Requests (Ward Reviewer+ can submit) ──────────────────────────
    Route::middleware(['role:ward_reviewer,ward_admin,municipal_admin,super_admin'])
        ->post('edit-requests', [EditRequestController::class, 'store'])
        ->name('edit-requests.store');

    // ─── Field Verification ──────────────────────────────────────────────────
    Route::middleware(['role:ward_reviewer,ward_admin,municipal_admin,super_admin'])->group(function () {
        Route::post('households/{household}/field-verify',   [FieldVerificationController::class, 'saveHousehold'])  ->name('households.field-verify');
        Route::post('institutions/{institution}/field-verify',[FieldVerificationController::class, 'saveInstitution'])->name('institutions.field-verify');
        Route::post('businesses/{business}/field-verify',    [FieldVerificationController::class, 'saveBusiness'])   ->name('businesses.field-verify');
    });

    // ─── Deceased status (ward_reviewer+, bypasses workflow) ────────────────────
    Route::middleware(['role:ward_reviewer,ward_admin,municipal_admin,super_admin', 'ward.scope'])
        ->group(function () {
            Route::patch('households/{household}/members/{member}/deceased', [HouseholdController::class, 'markDeceased'])->name('households.members.deceased');
            Route::delete('households/{household}/members/{member}/deceased', [HouseholdController::class, 'unmarkDeceased'])->name('households.members.undeceased');
        });

    // ─── Ward Reviewer + Ward Admin Routes (review/approve/reject) ─────────
    Route::middleware(['role:ward_reviewer,ward_admin,municipal_admin,super_admin', 'ward.scope'])
        ->prefix('review')->name('review.')->group(function () {
            Route::get('/', [ReviewController::class, 'index'])->name('index');
            Route::get('/households', [ReviewController::class, 'households'])->name('households');
            Route::get('/institutions', [ReviewController::class, 'institutions'])->name('institutions');
            Route::get('/businesses',  [ReviewController::class, 'businesses'])->name('businesses');
            Route::patch('/households/{household}/approve', [ReviewController::class, 'approveHousehold'])->name('households.approve');
            Route::patch('/households/{household}/reject', [ReviewController::class, 'rejectHousehold'])->name('households.reject');
            Route::patch('/households/{household}/escalate', [ReviewController::class, 'escalateHousehold'])->name('households.escalate');
            Route::patch('/institutions/{institution}/approve',  [ReviewController::class, 'approveInstitution']) ->name('institutions.approve');
            Route::patch('/institutions/{institution}/reject',  [ReviewController::class, 'rejectInstitution']) ->name('institutions.reject');
            Route::patch('/institutions/{institution}/escalate',[ReviewController::class, 'escalateInstitution'])->name('institutions.escalate');
            Route::post('/institutions/bulk-approve',           [ReviewController::class, 'bulkApproveInstitutions'])->name('institutions.bulk-approve');
            Route::post('/institutions/bulk-reject',            [ReviewController::class, 'bulkRejectInstitutions']) ->name('institutions.bulk-reject');
            Route::patch('/businesses/{business}/approve',      [ReviewController::class, 'approveBusiness'])   ->name('businesses.approve');
            Route::patch('/businesses/{business}/reject',       [ReviewController::class, 'rejectBusiness'])    ->name('businesses.reject');
            Route::patch('/businesses/{business}/escalate',     [ReviewController::class, 'escalateBusiness'])  ->name('businesses.escalate');
            Route::post('/businesses/bulk-approve',             [ReviewController::class, 'bulkApproveBusinesses'])->name('businesses.bulk-approve');
            Route::post('/businesses/bulk-reject',              [ReviewController::class, 'bulkRejectBusinesses']) ->name('businesses.bulk-reject');
        });

    // ─── Ward Admin + Municipal Admin shared routes ──────────────────────────
    // Publish actions, publish queue, edit requests — accessible to ward_admin too
    Route::middleware(['role:ward_admin,municipal_admin,super_admin', 'ward.scope'])
        ->prefix('admin')->name('admin.')->group(function () {
            Route::patch('/households/{household}/publish',       [HouseholdController::class,    'publish'])->name('households.publish');
            Route::patch('/institutions/{institution}/publish',   [InstitutionController::class,  'publish'])->name('institutions.publish');
            Route::patch('/businesses/{business}/publish',        [BusinessController::class,     'publish'])->name('businesses.publish');

            Route::get('/publish-queue', [PublishQueueController::class, 'index'])->name('publish-queue');
            Route::patch('/publish-queue/households/{household}/publish',     [PublishQueueController::class, 'publishHousehold'])   ->name('publish-queue.households.publish');
            Route::patch('/publish-queue/households/{household}/reject',      [PublishQueueController::class, 'rejectHousehold'])    ->name('publish-queue.households.reject');
            Route::patch('/publish-queue/institutions/{institution}/publish', [PublishQueueController::class, 'publishInstitution']) ->name('publish-queue.institutions.publish');
            Route::patch('/publish-queue/institutions/{institution}/reject',  [PublishQueueController::class, 'rejectInstitution'])  ->name('publish-queue.institutions.reject');
            Route::patch('/publish-queue/businesses/{business}/publish',      [PublishQueueController::class, 'publishBusiness'])    ->name('publish-queue.businesses.publish');
            Route::patch('/publish-queue/businesses/{business}/reject',       [PublishQueueController::class, 'rejectBusiness'])     ->name('publish-queue.businesses.reject');

            Route::post('/publish-queue/households/bulk-publish',     [PublishQueueController::class, 'bulkPublishHouseholds'])   ->name('publish-queue.households.bulk-publish');
            Route::post('/publish-queue/households/bulk-reject',      [PublishQueueController::class, 'bulkRejectHouseholds'])    ->name('publish-queue.households.bulk-reject');
            Route::post('/publish-queue/institutions/bulk-publish',   [PublishQueueController::class, 'bulkPublishInstitutions']) ->name('publish-queue.institutions.bulk-publish');
            Route::post('/publish-queue/institutions/bulk-reject',    [PublishQueueController::class, 'bulkRejectInstitutions'])  ->name('publish-queue.institutions.bulk-reject');
            Route::post('/publish-queue/businesses/bulk-publish',     [PublishQueueController::class, 'bulkPublishBusinesses'])   ->name('publish-queue.businesses.bulk-publish');
            Route::post('/publish-queue/businesses/bulk-reject',      [PublishQueueController::class, 'bulkRejectBusinesses'])    ->name('publish-queue.businesses.bulk-reject');

            Route::get('/edit-requests',                         [EditRequestController::class, 'index'])  ->name('edit-requests.index');
            Route::patch('/edit-requests/{editRequest}/approve', [EditRequestController::class, 'approve'])->name('edit-requests.approve');
            Route::patch('/edit-requests/{editRequest}/reject',  [EditRequestController::class, 'reject']) ->name('edit-requests.reject');
        });

    // ─── Feedback routes — ward_admin+ (hasPermission enforced inside controller) ───
    Route::middleware(['role:ward_admin,municipal_admin,super_admin'])
        ->prefix('admin')->name('admin.')->group(function () {
            Route::get('/feedback',               [AdminFeedbackController::class, 'index'])  ->name('feedback');
            Route::get('/feedback/export',        [AdminFeedbackController::class, 'export']) ->name('feedback.export');
            Route::patch('/feedback/{feedback}',  [AdminFeedbackController::class, 'update']) ->name('feedback.update');
            Route::delete('/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');
        });

    // ─── Admin Routes ────────────────────────────────────────────────────────
    Route::middleware(['role:municipal_admin,super_admin'])
        ->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
            Route::resource('users', UserController::class);
            Route::patch('/users/{user}/toggle-active',  [UserController::class, 'toggleActive'])->name('users.toggle-active');
            Route::patch('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::get('/ward-assignments',              [UserController::class, 'assignments'])  ->name('ward-assignments');
            Route::get('/progress',                      [ProgressController::class, 'index'])    ->name('progress');
            Route::get('/data-export',                   [DataExportController::class, 'index'])  ->name('data-export');
            Route::post('/data-export/download',         [DataExportController::class, 'download'])->name('data-export.download');
            Route::get('/audit-logs',                    [AuditLogController::class, 'index'])     ->name('audit-logs');
            Route::patch('/users/{user}/assign-wards',  [UserController::class, 'assignWards'])  ->name('users.assign-wards');
            Route::get('/role-permissions',              [RolePermissionsController::class, 'index'])->name('role-permissions');
            Route::patch('/role-permissions',            [RolePermissionsController::class, 'update'])->name('role-permissions.update');

            // Impersonation start (admin only)
            Route::post('/impersonate/{target}',   [ImpersonateController::class, 'start'])->name('impersonate.start');

            // Survey Options (super_admin only)
            Route::get('/survey-options',                        [SurveyOptionsController::class, 'index'])           ->name('survey-options');
            Route::post('/survey-options',                       [SurveyOptionsController::class, 'store'])           ->name('survey-options.store');
            Route::get('/survey-options/template',               [SurveyOptionsController::class, 'downloadTemplate'])->name('survey-options.template');
            Route::post('/survey-options/import',                [SurveyOptionsController::class, 'import'])          ->name('survey-options.import');
            Route::post('/survey-options/bulk-delete',           [SurveyOptionsController::class, 'bulkDestroy'])     ->name('survey-options.bulk-destroy');
            Route::post('/survey-options/reorder',               [SurveyOptionsController::class, 'reorder'])         ->name('survey-options.reorder');
            Route::put('/survey-options/{surveyOption}',         [SurveyOptionsController::class, 'update'])          ->name('survey-options.update');
            Route::patch('/survey-options/{surveyOption}/toggle',[SurveyOptionsController::class, 'toggle'])          ->name('survey-options.toggle');
            Route::delete('/survey-options/{surveyOption}',      [SurveyOptionsController::class, 'destroy'])         ->name('survey-options.destroy');

            // Google Sheets Sync
            Route::get('/google-sync',          [GoogleSyncController::class, 'index'])  ->name('google-sync');
            Route::get('/google-sync/manual',   [GoogleSyncController::class, 'manual']) ->name('google-sync.manual');
            Route::post('/google-sync/preview', [GoogleSyncController::class, 'preview'])->name('google-sync.preview');
            Route::post('/google-sync/apply',   [GoogleSyncController::class, 'apply'])  ->name('google-sync.apply');

            // User Manual
            Route::get('/user-manual', fn () => view('admin.user-manual'))->name('user-manual');

            // Bulk Import
            Route::get('/bulk-import', function () {
                return view('admin.bulk-import');
            })->name('bulk-import');
            Route::get('/institution-bulk-import', function () {
                return view('admin.institution-bulk-import');
            })->name('institution-bulk-import');
            Route::get('/business-bulk-import', function () {
                return view('admin.business-bulk-import');
            })->name('business-bulk-import');

            // Maintenance Mode
            Route::patch('/settings/maintenance',        [SettingsController::class, 'updateMaintenance'])   ->name('settings.maintenance');
            Route::post('/settings/maintenance/bg',      [SettingsController::class, 'uploadMaintenanceBg']) ->name('settings.maintenance.bg');
            Route::delete('/settings/maintenance/bg',    [SettingsController::class, 'deleteMaintenanceBg']) ->name('settings.maintenance.bg.delete');
            Route::get('/maintenance-preview', fn () => view('maintenance', [
                'lg' => \App\Models\LocalGovernment::getFirstCached(),
            ]))->name('maintenance.preview');

            // Municipal Settings
            Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::post('/settings/lock',   [SettingsController::class, 'lock'])  ->name('settings.lock');
            Route::post('/settings/unlock', [SettingsController::class, 'unlock'])->name('settings.unlock');
            Route::patch('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general');
            Route::post('/settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo');
            Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
            Route::patch('/settings/map',          [SettingsController::class, 'updateMap'])         ->name('settings.map');
            Route::post('/settings/map-geojson',   [SettingsController::class, 'uploadMapGeojson']) ->name('settings.map-geojson');
            Route::delete('/settings/map-geojson', [SettingsController::class, 'deleteMapGeojson']) ->name('settings.map-geojson.delete');
            Route::patch('/settings/photo-dir',  [SettingsController::class, 'updatePhotoDir'])   ->name('settings.photo-dir');
            Route::patch('/settings/welcome',           [SettingsController::class, 'updateWelcome'])       ->name('settings.welcome');
            Route::patch('/settings/login-branding',    [SettingsController::class, 'updateLoginBranding'])->name('settings.login-branding');
            Route::post('/settings/login-landmark',     [SettingsController::class, 'uploadLoginLandmark'])->name('settings.login-landmark');
            Route::delete('/settings/login-landmark',   [SettingsController::class, 'deleteLoginLandmark'])->name('settings.login-landmark.delete');
            Route::post('/settings/leader-photo', [SettingsController::class, 'uploadLeaderPhoto'])->name('settings.leader-photo');
            Route::post('/settings/map-image',    [SettingsController::class, 'uploadMapImage'])   ->name('settings.map-image');
            Route::delete('/settings/map-image',  [SettingsController::class, 'deleteMapImage'])   ->name('settings.map-image.delete');
            Route::post('/settings/wards', [SettingsController::class, 'storeWard'])->name('settings.wards.store');
            Route::delete('/settings/wards/bulk',                [SettingsController::class, 'bulkDestroyWards'])           ->name('settings.wards.bulk-destroy');
            Route::get('/settings/wards/template',               [SettingsController::class, 'downloadWardsTemplate'])           ->name('settings.wards.template');
            Route::post('/settings/wards/import',                [SettingsController::class, 'importWards'])                     ->name('settings.wards.import');
            Route::patch('/settings/wards/{ward}', [SettingsController::class, 'updateWard'])->name('settings.wards.update');
            Route::delete('/settings/wards/{ward}', [SettingsController::class, 'destroyWard'])->name('settings.wards.destroy');
            Route::get('/settings/wards-settlements/template',   [SettingsController::class, 'downloadWardSettlementsTemplate'])  ->name('settings.wards-settlements.template');
            Route::post('/settings/wards-settlements/import',    [SettingsController::class, 'importWardsAndSettlements'])        ->name('settings.wards-settlements.import');
            Route::delete('/settings/settlements/bulk',          [SettingsController::class, 'bulkDestroySettlements'])       ->name('settings.settlements.bulk-destroy');
            Route::post('/settings/wards/{ward}/settlements',   [SettingsController::class, 'storeSettlement'])          ->name('settings.settlements.store');
            Route::patch('/settings/settlements/{settlement}',   [SettingsController::class, 'updateSettlement'])         ->name('settings.settlements.update');
            Route::delete('/settings/settlements/{settlement}',  [SettingsController::class, 'destroySettlement'])        ->name('settings.settlements.destroy');
            Route::get('/settings/settlements/template',         [SettingsController::class, 'downloadSettlementsTemplate'])->name('settings.settlements.template');
            Route::post('/settings/settlements/import',          [SettingsController::class, 'importSettlements'])        ->name('settings.settlements.import');
            Route::get('/settings/api/local-governments', [SettingsController::class, 'apiLocalGovernments'])->name('settings.api.lgs');
            Route::post('/settings/representatives',                  [SettingsController::class, 'storeRepresentative'])  ->name('settings.representatives.store');
            Route::post('/settings/representatives/{representative}', [SettingsController::class, 'updateRepresentative']) ->name('settings.representatives.update');
            Route::delete('/settings/representatives/{representative}',[SettingsController::class, 'destroyRepresentative'])->name('settings.representatives.destroy');
            Route::post('/settings/employees',              [SettingsController::class, 'storeEmployee'])  ->name('settings.employees.store');
            Route::post('/settings/employees/{employee}',   [SettingsController::class, 'updateEmployee']) ->name('settings.employees.update');
            Route::delete('/settings/employees/{employee}', [SettingsController::class, 'destroyEmployee'])->name('settings.employees.destroy');
            Route::post('/settings/gallery',                [SettingsController::class, 'storePhoto'])     ->name('settings.gallery.store');
            Route::post('/settings/gallery/{lgPhoto}',      [SettingsController::class, 'updatePhoto'])    ->name('settings.gallery.update');
            Route::delete('/settings/gallery/{lgPhoto}',    [SettingsController::class, 'destroyPhoto'])   ->name('settings.gallery.destroy');
            Route::post('/settings/download-links',                    [SettingsController::class, 'storeDownloadLink'])  ->name('settings.download-links.store');
            Route::post('/settings/download-links/{downloadLink}',     [SettingsController::class, 'updateDownloadLink']) ->name('settings.download-links.update');
            Route::delete('/settings/download-links/{downloadLink}',   [SettingsController::class, 'destroyDownloadLink'])->name('settings.download-links.destroy');
            Route::patch('/settings/survey-period', [SettingsController::class, 'saveSurveyPeriod'])->name('settings.survey-period.save');
            Route::post('/settings/rounds',              [SurveyRoundController::class, 'store'])  ->name('settings.rounds.store');
            Route::delete('/settings/rounds/{number}',   [SurveyRoundController::class, 'destroy'])->name('settings.rounds.destroy');
            Route::get('/settings/export',  [SettingsController::class, 'exportSettings']) ->name('settings.export');
            Route::post('/settings/import', [SettingsController::class, 'importSettings']) ->name('settings.import');
            Route::patch('/settings/google-sheets',      [SettingsController::class, 'saveGoogleSheets'])  ->name('settings.google-sheets.save');
            Route::post('/settings/test-google-sheet',   [SettingsController::class, 'testGoogleSheet'])   ->name('settings.google-sheets.test');
            Route::patch('/settings/user-manual',        [SettingsController::class, 'updateUserManual'])  ->name('settings.user-manual');

            // License
            Route::post('/settings/license/generate',  [LicenseController::class, 'generate']) ->name('settings.license.generate');
            Route::post('/settings/license/activate',  [LicenseController::class, 'activate']) ->name('settings.license.activate');
        });
});

require __DIR__.'/auth.php';
