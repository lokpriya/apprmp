/**
 * Test 2: Authenticated admin/enumerator routes
 * Simulates real users: login → browse → submit
 *
 * Run: k6 run tests/load/2_auth_admin_load.js
 * Override credentials: k6 run -e ADMIN_EMAIL=admin@example.com -e ADMIN_PASS=secret tests/load/2_auth_admin_load.js
 */

import http from 'k6/http'
import { check, sleep, fail } from 'k6'
import { Trend, Rate, Counter } from 'k6/metrics'

// ── Config ────────────────────────────────────────────────────────────────────
const BASE_URL    = __ENV.BASE_URL    || 'http://localhost:8000'
const ADMIN_EMAIL = __ENV.ADMIN_EMAIL || 'admin@test.com'
const ADMIN_PASS  = __ENV.ADMIN_PASS  || 'password'

export const options = {
  stages: [
    { duration: '10s', target: 5  },
    { duration: '30s', target: 15 },
    { duration: '20s', target: 15 },
    { duration: '10s', target: 0  },
  ],
  thresholds: {
    http_req_failed:   ['rate<0.05'],
    http_req_duration: ['p(95)<4000'],
    login_failures:    ['count<3'],
  },
}

const pageDuration  = new Trend('page_duration', true)
const loginFailures = new Counter('login_failures')

// ── Auth helper ───────────────────────────────────────────────────────────────
function getCSRF(jar) {
  const res = http.get(`${BASE_URL}/login`, { cookieJar: jar })
  const match = res.body.match(/name="?_token"?\s+value="?([^">\s]+)"?/)
  if (!match) {
    console.error('Could not extract CSRF token from login page')
    return null
  }
  return match[1]
}

function login(jar) {
  const csrf = getCSRF(jar)
  if (!csrf) return false

  const res = http.post(
    `${BASE_URL}/login`,
    { email: ADMIN_EMAIL, password: ADMIN_PASS, _token: csrf },
    {
      cookieJar: jar,
      redirects: 5,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    }
  )

  // A successful Breeze login redirects to /admin or /households
  const ok = res.url.includes('/admin') || res.url.includes('/households') || res.status === 200
  if (!ok) {
    loginFailures.add(1)
    console.error(`Login failed: ${res.status} → ${res.url}`)
  }
  return ok
}

// ── Admin browsing scenario ───────────────────────────────────────────────────
function adminScenario(jar) {
  const ADMIN_PAGES = [
    '/admin',
    '/admin/progress',
    '/admin/publish-queue',
    '/admin/edit-requests',
    '/admin/audit-logs',
    '/admin/users',
    '/admin/data-export',
  ]

  const page = ADMIN_PAGES[Math.floor(Math.random() * ADMIN_PAGES.length)]
  const res  = http.get(`${BASE_URL}${page}`, { cookieJar: jar })
  pageDuration.add(res.timings.duration, { type: 'admin' })
  check(res, { 'admin page reachable': (r) => r.status === 200 || r.status === 302 })
  sleep(Math.random() * 2 + 1)
}

// ── Enumerator browsing scenario ──────────────────────────────────────────────
function enumeratorScenario(jar) {
  // List page
  const listRes = http.get(`${BASE_URL}/households`, { cookieJar: jar })
  pageDuration.add(listRes.timings.duration, { type: 'list' })
  check(listRes, { 'households list 200': (r) => r.status === 200 })

  sleep(1)

  // Institutions list
  const instRes = http.get(`${BASE_URL}/institutions`, { cookieJar: jar })
  pageDuration.add(instRes.timings.duration, { type: 'list' })
  check(instRes, { 'institutions list 200': (r) => r.status === 200 })

  sleep(Math.random() * 1.5 + 0.5)
}

// ── Main VU loop ──────────────────────────────────────────────────────────────
export default function () {
  const jar = http.cookieJar()

  if (!login(jar)) {
    console.warn('Skipping VU iteration — login failed')
    sleep(3)
    return
  }

  // Each VU does 3–5 page visits after login
  const iterations = Math.floor(Math.random() * 3) + 3
  for (let i = 0; i < iterations; i++) {
    if (Math.random() < 0.4) adminScenario(jar)
    else enumeratorScenario(jar)
  }

  // Logout to free session
  const logoutCSRF = (() => {
    const page  = http.get(`${BASE_URL}/admin`, { cookieJar: jar })
    const match = page.body.match(/name="?_token"?\s+value="?([^">\s]+)"?/)
    return match ? match[1] : ''
  })()

  if (logoutCSRF) {
    http.post(`${BASE_URL}/logout`, { _token: logoutCSRF }, { cookieJar: jar, redirects: 3 })
  }

  sleep(1)
}
