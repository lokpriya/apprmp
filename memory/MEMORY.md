# Project: Local Government Profile Management System
# स्थानीय तह वस्तुगत विवरण तथा विश्लेषण प्रणाली

## Environment
- PHP: 8.5.1 NTS at `D:\xampp\php-8.2\php.exe`
- Composer: 2.9.5 at `C:\ProgramData\ComposerSetup\bin\composer.phar`
- XAMPP: Apache uses PHP 8.0 (D:\xampp\php\); CLI uses PHP 8.5 (D:\xampp\php-8.2\)
- Start server: `D:\xampp\php-8.2\php.exe artisan serve` in project root
- App URL: http://localhost:8000
- DB: MySQL via XAMPP, database=apprmp, user=root, no password
- SESSION_DRIVER=database (sessions table created manually)

## Stack
- Laravel 12 + Livewire 4 + Alpine.js 3 (CDN) + Tailwind CSS (CDN)
- No Node.js installed → @vite replaced with CDN in all blade layouts
- Packages: livewire/livewire, maatwebsite/excel, spatie/laravel-activitylog, mews/purifier, laravel/breeze (blade), laravel/sanctum

## Project Path
D:\XAMPP\htdocs\apprmp

## Roles
- super_admin, municipal_admin, ward_reviewer, enumerator
- Test accounts (password: `password`):
  - superadmin@test.com (super_admin)
  - admin@test.com (municipal_admin)
  - reviewer@test.com (ward_reviewer, wards 1-3)
  - enumerator@test.com (enumerator, ward 1)

## Build Status — ALL COMPLETE ✓
- [x] 36 migrations + 29 models + 7 enums
- [x] Middleware: EnsureRole, ScopeToWard, CheckDataFreeze
- [x] Services: UniqueCodeService, WorkflowService, HouseholdService, InstitutionService
- [x] Livewire: HouseholdForm (7-step), InstitutionForm (5-step)
- [x] Controllers: all implemented (households, institutions, review, admin, public)
- [x] Seeders run: 15 institution types, Ilam Municipality + 10 wards + 20 settlements, 4 test users
- [x] Views: sidebar layout, households CRUD, institutions CRUD, review queue, admin dashboard, user management, public portal with Chart.js
- [x] Components: x-detail-row, x-form.select
- [x] 71 routes registered and verified

## Key Files
- `app/Services/HouseholdService.php` - create/update households + all sub-tables
- `app/Services/InstitutionService.php` - create/update institutions + all sub-tables
- `app/Services/WorkflowService.php` - status transitions + SubmissionLog
- `app/Livewire/HouseholdForm.php` + `resources/views/livewire/household-form.blade.php`
- `app/Livewire/InstitutionForm.php` + `resources/views/livewire/institution-form.blade.php`
- `resources/views/layouts/sidebar.blade.php` - left nav (ml-64)
- `resources/views/public/dashboard.blade.php` - Chart.js analytics (no auth)
- `routes/web.php` - 71 routes

## Known Issues Fixed
- ext-bcmath: built-in to PHP 8.5 (no DLL needed)
- Sessions table: created manually via PDO
- Migration order: households 074850, institutions 074905
- No Node.js: @vite replaced with CDN (Tailwind CDN + Alpine.js CDN)

## Potential Next Steps
- Bulk Import (Maatwebsite Excel) for households/institutions
- API (Sanctum) for offline sync via sync_queues table
- Map view with Leaflet.js + household GPS points
- SMS/email notifications on workflow transitions
- Export to PDF/Excel (published records)
