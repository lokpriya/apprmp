/**
 * Test 3: Stress test — finds the breaking point
 * Keeps ramping up VUs until error rate climbs above threshold.
 *
 * Run: k6 run tests/load/3_stress_test.js
 *
 * What to watch in MySQL during this test:
 *   SHOW PROCESSLIST;
 *   SHOW STATUS LIKE 'Threads_connected';
 *   SHOW STATUS LIKE 'Slow_queries';
 */

import http from 'k6/http'
import { check, sleep } from 'k6'
import { Rate, Trend } from 'k6/metrics'

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000'

export const options = {
  // Stress: keep doubling until we see failures
  stages: [
    { duration: '10s', target: 10  },
    { duration: '20s', target: 20  },
    { duration: '20s', target: 40  },
    { duration: '20s', target: 80  },
    { duration: '20s', target: 120 },
    { duration: '30s', target: 120 }, // hold peak
    { duration: '20s', target: 0   }, // recovery
  ],
  thresholds: {
    // These are intentionally loose — we WANT to find where it breaks
    http_req_failed:   ['rate<0.30'],      // fail test if >30% errors
    http_req_duration: ['p(99)<10000'],    // fail if p99 > 10s
  },
}

const errorRate    = new Rate('error_rate')
const responsetime = new Trend('response_time', true)

// Heavy analytics routes — most representative of real load
const ROUTES = [
  '/public',
  '/public/housing',
  '/public/institution-reports',
  '/public/institution-reports/by-type',
  '/public/institution-reports/staff',
  '/public/business-reports',
  '/public/business-reports/by-field',
  '/public/data/population',
  '/public/data/institutions',
]

export default function () {
  const url = BASE_URL + ROUTES[Math.floor(Math.random() * ROUTES.length)]
  const res = http.get(url, {
    headers: { 'Accept': 'text/html,application/json' },
    timeout: '10s',
  })

  responsetime.add(res.timings.duration)
  errorRate.add(res.status >= 400 || res.status === 0)

  check(res, {
    'status not 5xx': (r) => r.status < 500,
    'status not 429': (r) => r.status !== 429,
    'under 5s':       (r) => r.timings.duration < 5000,
  })

  sleep(Math.random() * 1 + 0.2) // minimal think time = maximum pressure
}

export function handleSummary(data) {
  const req      = data.metrics.http_reqs
  const dur      = data.metrics.http_req_duration
  const failed   = data.metrics.http_req_failed

  console.log('\n══════════ STRESS TEST SUMMARY ══════════')
  console.log(`Total requests : ${req.values.count}`)
  console.log(`Req/sec (avg)  : ${req.values.rate.toFixed(1)}`)
  console.log(`Error rate     : ${(failed.values.rate * 100).toFixed(2)}%`)
  console.log(`Median latency : ${dur.values['med'].toFixed(0)}ms`)
  console.log(`p95 latency    : ${dur.values['p(95)'].toFixed(0)}ms`)
  console.log(`p99 latency    : ${dur.values['p(99)'].toFixed(0)}ms`)
  console.log(`Max latency    : ${dur.values['max'].toFixed(0)}ms`)
  console.log('═════════════════════════════════════════\n')

  return {}
}
