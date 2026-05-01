/**
 * Test 1: Public routes — no authentication needed
 * Run: k6 run tests/load/1_public_load.js
 * Run with more VUs: k6 run --vus 30 --duration 60s tests/load/1_public_load.js
 */

import http from 'k6/http'
import { check, sleep } from 'k6'
import { Trend, Rate } from 'k6/metrics'

// ── Config ────────────────────────────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'http://localhost:8000'

export const options = {
  stages: [
    { duration: '15s', target: 10 },   // ramp up to 10 users
    { duration: '30s', target: 20 },   // hold 20 users
    { duration: '15s', target: 40 },   // spike to 40
    { duration: '20s', target: 40 },   // hold spike
    { duration: '10s', target: 0  },   // ramp down
  ],
  thresholds: {
    http_req_failed:   ['rate<0.05'],           // <5% errors
    http_req_duration: ['p(95)<3000'],          // 95% under 3s
    'page_duration{type:heavy}': ['p(95)<5000'],// heavy analytics under 5s
  },
}

// Custom metrics
const pageDuration = new Trend('page_duration', true)
const errorRate    = new Rate('error_rate')

const HEADERS = {
  'Accept':          'text/html,application/xhtml+xml',
  'Accept-Language': 'ne,en;q=0.9',
}

// Routes grouped by expected load
const LIGHT_ROUTES = [
  '/public',
  '/public/gallery',
  '/public/downloads',
  '/public/employees',
  '/public/map',
]

const HEAVY_ROUTES = [
  '/public/housing',
  '/public/institution-reports',
  '/public/institution-reports/by-type',
  '/public/institution-reports/by-ownership',
  '/public/institution-reports/staff',
  '/public/institution-reports/infrastructure',
  '/public/institution-reports/services',
  '/public/business-reports',
  '/public/business-reports/by-type',
  '/public/business-reports/by-field',
  '/public/business-reports/by-location',
  '/public/business-reports/registration',
]

// Data API endpoints (JSON, hit by the SPA)
const API_ROUTES = [
  '/public/data/population',
  '/public/data/institutions',
]

function pickRandom(arr) {
  return arr[Math.floor(Math.random() * arr.length)]
}

export default function () {
  const scenario = Math.random()

  if (scenario < 0.5) {
    // 50% — light page
    const url = BASE_URL + pickRandom(LIGHT_ROUTES)
    const res = http.get(url, { headers: HEADERS, tags: { type: 'light' } })
    pageDuration.add(res.timings.duration, { type: 'light' })
    errorRate.add(res.status !== 200)
    check(res, { 'light page 200': (r) => r.status === 200 })

  } else if (scenario < 0.85) {
    // 35% — heavy analytics page
    const url = BASE_URL + pickRandom(HEAVY_ROUTES)
    const res = http.get(url, { headers: HEADERS, tags: { type: 'heavy' } })
    pageDuration.add(res.timings.duration, { type: 'heavy' })
    errorRate.add(res.status !== 200)
    check(res, { 'analytics page 200': (r) => r.status === 200 })

  } else {
    // 15% — data API
    const url = BASE_URL + pickRandom(API_ROUTES)
    const res = http.get(url, {
      headers: { ...HEADERS, 'Accept': 'application/json' },
      tags: { type: 'api' }
    })
    pageDuration.add(res.timings.duration, { type: 'api' })
    check(res, { 'api 200': (r) => r.status === 200 })
  }

  sleep(Math.random() * 2 + 0.5) // 0.5–2.5s think time
}
