/**
 * syncManager.js — drains the offline submission queue when connectivity returns.
 *
 * Usage (in AppLayout):
 *   const { pendingCount, drainQueue, refreshCount } = useSyncManager()
 */

import { ref } from 'vue'
import { getQueue, getQueueCount, dequeue, incrementAttempts } from './draftStore'

// Shared reactive count — same ref reused across all component instances
const pendingCount = ref(0)
let   _draining    = false

export function useSyncManager() {

  async function refreshCount() {
    pendingCount.value = await getQueueCount()
  }

  async function drainQueue() {
    if (_draining) return
    await refreshCount()
    if (pendingCount.value === 0) return

    _draining = true
    let synced  = 0
    let failed  = 0

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
    const items     = await getQueue()

    for (const item of items) {
      try {
        const res = await fetch(item.url, {
          method:   item.method,
          redirect: 'follow',
          headers: {
            'Content-Type':    'application/json',
            'Accept':          'application/json, text/plain, */*',
            'X-CSRF-TOKEN':    csrfToken,
            'X-Inertia':       'true',
            'X-Requested-With':'XMLHttpRequest',
          },
          body: JSON.stringify(item.data),
        })

        if (res.ok || res.status === 302) {
          // Success — remove from queue
          await dequeue(item.id)
          synced++
        } else if (res.status === 422) {
          // Permanent validation failure — remove and count as failed
          await dequeue(item.id)
          failed++
          console.warn('[syncManager] Validation error for queued item:', item.url, await res.json())
        } else {
          // Transient server error — keep in queue, record attempt
          await incrementAttempts(item.id, `HTTP ${res.status}`)
        }
      } catch (err) {
        // Network still unavailable or fetch failed — keep in queue
        await incrementAttempts(item.id, err.message)
      }
    }

    _draining = false
    await refreshCount()

    return { synced, failed }
  }

  return { pendingCount, refreshCount, drainQueue }
}
