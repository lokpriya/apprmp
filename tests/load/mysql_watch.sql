-- Run this in MySQL Workbench or phpMyAdmin BEFORE starting load tests
-- to enable slow query logging with a 0.5s threshold

SET GLOBAL slow_query_log      = 'ON';
SET GLOBAL long_query_time     = 0.5;      -- log queries slower than 500ms
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Check current connection count
SHOW STATUS LIKE 'Threads_connected';
SHOW STATUS LIKE 'Max_used_connections';

-- ── Watch live during test (run in a separate tab) ────────────────────────────

-- Top 10 slowest queries in the process list right now:
SELECT id, user, host, db, time, state, LEFT(info, 100) AS query
FROM information_schema.processlist
WHERE command != 'Sleep'
ORDER BY time DESC
LIMIT 10;

-- Table-level lock waits (run repeatedly):
SHOW STATUS LIKE 'Table_locks_waited';

-- InnoDB row lock waits:
SHOW STATUS LIKE 'Innodb_row_lock_waits';
SHOW STATUS LIKE 'Innodb_row_lock_time_avg';

-- Full query stats snapshot:
SELECT
  ROUND(SUM(timer_wait)/1000000000000, 2) AS total_sec,
  COUNT_STAR                               AS exec_count,
  LEFT(digest_text, 120)                   AS query_pattern
FROM performance_schema.events_statements_summary_by_digest
WHERE schema_name = 'apprmp'
ORDER BY total_sec DESC
LIMIT 20;

-- ── After tests: see what was slow ───────────────────────────────────────────
-- Check your slow query log file in D:\XAMPP\mysql\data\ORION-slow.log

-- Disable when done:
-- SET GLOBAL slow_query_log = 'OFF';
