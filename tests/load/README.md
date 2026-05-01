# Load & Stress Tests

## Quick start

### 1. Install k6 (Windows)
```powershell
winget install k6
# OR: download k6.exe from https://github.com/grafana/k6/releases and add to PATH
```

### 2. Make sure the app is running
```bash
# In one terminal — keep running:
php artisan serve
# App will be at http://localhost:8000
```

### 3. Enable MySQL monitoring (optional but recommended)
Open phpMyAdmin → SQL tab → paste contents of `mysql_watch.sql`

---

## Test 1 — Public load (no auth)
Tests the public dashboard and analytics pages under concurrent visitors.

```bash
# Quick smoke (10 VUs, 30s)
k6 run tests/load/1_public_load.js

# Harder (override VUs/duration)
k6 run --vus 50 --duration 120s tests/load/1_public_load.js

# With HTML report
k6 run --out json=tests/load/results_public.json tests/load/1_public_load.js
```

**What to watch for:**
- p95 latency climbing above 3s on `/public/housing` or analytics pages
- `error_rate` rising (Laravel returning 500/503)
- MySQL `Threads_connected` maxing out

---

## Test 2 — Authenticated admin load
Simulates real users logging in and browsing admin/enumerator pages.

```bash
# Uses TestUsersSeeder credentials by default (admin@test.com / password)
k6 run tests/load/2_auth_admin_load.js

# Override for a different user (e.g. reviewer):
k6 run -e ADMIN_EMAIL=reviewer@test.com -e ADMIN_PASS=password tests/load/2_auth_admin_load.js
```

**What to watch for:**
- `login_failures` counter > 0 (means CSRF or session issue under load)
- Session table (`sessions`) row count exploding
- `/admin/publish-queue` or `/admin/progress` being slow (aggregation queries)

---

## Test 3 — Stress test (find the breaking point)
Ramps up to 120 virtual users. Expect errors at high VUs — that's the point.

```bash
k6 run tests/load/3_stress_test.js
```

**Typical failure modes for XAMPP/Apache + mod_php:**
- MySQL `Too many connections` error (~151 default limit)
- PHP process count hitting Apache `MaxRequestWorkers` (~150 default)
- Laravel queue/session table lock contention
- Analytics queries without indexes causing full table scans

---

## Interpreting results

| Metric | Good | Warning | Bad |
|--------|------|---------|-----|
| p95 latency (public) | < 1.5s | 1.5–3s | > 3s |
| p95 latency (admin) | < 2s | 2–4s | > 4s |
| Error rate | < 1% | 1–5% | > 5% |
| Max latency | < 5s | 5–10s | > 10s |

---

## After testing

```sql
-- Turn off slow query log
SET GLOBAL slow_query_log = 'OFF';
```

Check slow log at: `D:\XAMPP\mysql\data\ORION-slow.log`

Use `EXPLAIN` on any slow queries found:
```sql
EXPLAIN SELECT ... (paste slow query here)
```
