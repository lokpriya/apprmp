/**
 * draftStore.js — IndexedDB-backed storage for:
 *   1. Form drafts  (store: 'drafts')
 *   2. Offline submission queue  (store: 'sync_queue')
 */

import { openDB } from 'idb'

const DB_NAME    = 'apprmp_drafts'
const DB_VERSION = 2   // bumped from 1 → adds sync_queue store

let _db = null

function getDB() {
  if (_db) return Promise.resolve(_db)
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db, oldVersion) {
      if (oldVersion < 1) {
        db.createObjectStore('drafts')
      }
      if (oldVersion < 2) {
        // sync_queue uses keyPath so each item carries its own id
        db.createObjectStore('sync_queue', { keyPath: 'id' })
      }
    },
  }).then(db => {
    _db = db
    return db
  })
}

// ── Draft helpers ─────────────────────────────────────────────────────────────

/** Save any serialisable object under key. */
export async function saveDraft(key, data) {
  const db = await getDB()
  await db.put('drafts', data, key)
}

/** Return the stored object, or null if not found. */
export async function getDraft(key) {
  const db  = await getDB()
  const val = await db.get('drafts', key)
  return val ?? null
}

/** Remove a draft entry. */
export async function deleteDraft(key) {
  const db = await getDB()
  await db.delete('drafts', key)
}

/** Check if a draft exists without loading it. */
export async function hasDraft(key) {
  const db  = await getDB()
  const val = await db.get('drafts', key)
  return val !== undefined && val !== null
}

// ── Sync queue helpers ────────────────────────────────────────────────────────

/**
 * Add a pending submission to the queue.
 * @param {{ type: string, method: string, url: string, data: object }} item
 */
export async function enqueue(item) {
  const db = await getDB()
  const record = {
    ...item,
    id:        crypto.randomUUID(),
    queuedAt:  new Date().toISOString(),
    attempts:  0,
    lastError: null,
  }
  await db.put('sync_queue', record)
  return record.id
}

/** Return all pending items, oldest first. */
export async function getQueue() {
  const db = await getDB()
  const all = await db.getAll('sync_queue')
  return all.sort((a, b) => a.queuedAt.localeCompare(b.queuedAt))
}

/** Count pending items. */
export async function getQueueCount() {
  const db = await getDB()
  return db.count('sync_queue')
}

/** Remove an item after successful sync. */
export async function dequeue(id) {
  const db = await getDB()
  await db.delete('sync_queue', id)
}

/** Increment attempt counter and optionally save last error. */
export async function incrementAttempts(id, errorMsg = null) {
  const db   = await getDB()
  const item = await db.get('sync_queue', id)
  if (!item) return
  item.attempts++
  item.lastError = errorMsg
  await db.put('sync_queue', item)
}
