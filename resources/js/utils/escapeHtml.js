/**
 * Escape text for use inside v-html.
 *
 * Several pages highlight a search match by wrapping it in <mark> and rendering
 * the result with v-html. They passed names typed into the admin panel straight
 * through, so a name such as <img src=x onerror=…> ran as script on the public
 * portal — and the login headline did the same on the login page, where a
 * script can read the password as it is typed. Escape first, then add markup.
 */
export function escapeHtml(text) {
  return String(text ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}

/** Escaped text with every case-insensitive occurrence of `query` wrapped in `<mark class=…>`. */
export function highlightHtml(text, query, markClass) {
  const safe = escapeHtml(text)
  const q = escapeHtml(String(query ?? '').trim())
  if (!q) return safe
  const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')
  return safe.replace(re, m => `<mark class="${markClass}">${m}</mark>`)
}
