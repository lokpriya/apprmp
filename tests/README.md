# Test suite

## One-time setup

The suite runs against a **real MariaDB database**, not SQLite. Create it once:

```sql
CREATE DATABASE apprmp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
D:\MariaDB12\bin\mysql.exe -u root -p --port=3307 \
  -e "CREATE DATABASE IF NOT EXISTS apprmp_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

`phpunit.xml` sets only `DB_CONNECTION=mysql` and `DB_DATABASE=apprmp_test`; the
host, port, username and password are inherited from `.env`, so no credentials
live in a committed file.

## Running

```bash
D:\xampp\php-8.2\php.exe vendor/bin/phpunit
D:\xampp\php-8.2\php.exe vendor/bin/phpunit --filter=ResidingCountryCastTest
```

The first database-backed test migrates the whole schema, which takes about
three minutes; every test after that runs inside a transaction, so adding tests
is nearly free. Filter to a Unit test if you want a fast loop — the `tests/Unit`
tests touch no database and finish in well under a second.

## Why not SQLite

Two reasons, both of which would produce false confidence rather than a clean
failure:

1. Several migrations use raw MySQL DDL (`ALTER TABLE ... CHANGE`) that SQLite
   cannot parse — the suite errored on 23 of 25 tests before this change.
2. The public reports depend on MariaDB JSON functions (`JSON_EXTRACT`,
   `JSON_UNQUOTE`). A green SQLite suite would say nothing about whether they
   work in production.

## Safety guard

`Tests\TestCase::refreshApplication()` refuses to run unless the target database
name ends in `_test`. `RefreshDatabase` drops and rebuilds every table it
touches, so a mistyped `DB_DATABASE` would otherwise destroy the development
database. The check runs before `RefreshDatabase` boots, so an unsafe target
aborts before any table is dropped.

## What is covered

These are regression tests, written against bugs that reached production — not
coverage for its own sake.

| Test | Guards against |
| --- | --- |
| `Unit/ResidingCountryCastTest` | Migration destinations silently lost when the `array` cast was dropped. Live rows hold JSON *scalars*, so the accessor must read both scalars and arrays losslessly. |
| `Unit/ReportCacheKeyTest` | A per-LG breakdown and its own page sharing one cache key, so the breakdown overwrote the page's data. Prefixes must be unique within a controller. |
| `Unit/CsvInjectionTest` | Formula injection in the public open-data CSVs, which carry enumerator-entered free text and are downloadable without authentication. |
| `Unit/SourceConventionsTest` | Patterns already fixed once and then reintroduced: raw `storage_path('app/public')` (ignores the `PUBLIC_DISK_ROOT` override and blanks every logo), `LocalGovernment::first()`, and `env()` outside `config/`. |
| `Unit/UploadLimitsTest` | `upload_max_filesize` being smaller than a validation rule. PHP discards an oversized upload before Laravel runs, so the user sees no error at all — this is what broke the landmark photo and silently capped bulk import at 2 MB. |
| `Unit/SentryScrubberTest` | Household PII (names, phones, GPS, citizenship numbers) reaching Sentry's servers abroad. A failure here means error reporting is leaking personal data. |
| `Feature/HealthCheckTest` | The `/healthz` contract: status codes are what uptime monitoring alerts on, and a stalled queue must report `degraded` rather than pass silently. |
| `Feature/InstitutionTypeValidationTest` | `institution_type` reaching the column unvalidated, and one LG's custom options leaking into another's. |
| `Feature/LoginBrandingUploadTest` | The landmark photo failing to save, saving the branding text wiping the photo, and a replaced photo leaking files onto disk. |

## Writing a feature test that touches an admin route

Admin roles are forced through 2FA. A user without `two_factor_confirmed_at` is
redirected to `/two-factor/setup` and never reaches your controller — which turns
assertions into false passes rather than failures. Create admins like this:

```php
User::factory()->create([
    'role' => 'super_admin',
    'local_government_id' => $lgId,
    'two_factor_secret' => encrypt('TESTSECRET'),
    'two_factor_confirmed_at' => now(),
]);
```

There are no factories beyond `UserFactory`, and `local_governments` requires a
`district`, which requires a `province`. See `LoginBrandingUploadTest::setUp()`
for the shortest chain that satisfies the foreign keys.

When you fix a production bug, add its case here. The two most expensive bugs in
this codebase both regressed *after* being fixed, because the fix lived in a
single line with nothing asserting it.
