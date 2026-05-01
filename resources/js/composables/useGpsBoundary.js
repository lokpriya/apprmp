import { ref } from 'vue'

// ── Geometry helpers ──────────────────────────────────────────────────────────

function pointInRing(lat, lng, ring) {
  // GeoJSON ring: [[lng, lat], ...]
  let inside = false
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i][0], yi = ring[i][1]
    const xj = ring[j][0], yj = ring[j][1]
    if (((yi > lat) !== (yj > lat)) &&
        (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) {
      inside = !inside
    }
  }
  return inside
}

function haversineM(lat1, lng1, lat2, lng2) {
  const R = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

function distPointToSegmentM(lat, lng, lat1, lng1, lat2, lng2) {
  const dx = lat2 - lat1, dy = lng2 - lng1
  const lenSq = dx * dx + dy * dy
  const t = lenSq > 0 ? Math.max(0, Math.min(1, ((lat - lat1) * dx + (lng - lng1) * dy) / lenSq)) : 0
  return haversineM(lat, lng, lat1 + t * dx, lng1 + t * dy)
}

function distToRingM(lat, lng, ring) {
  let min = Infinity
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    // GeoJSON: [lng, lat]
    const d = distPointToSegmentM(lat, lng, ring[i][1], ring[i][0], ring[j][1], ring[j][0])
    if (d < min) min = d
  }
  return min
}

// ── Composable ────────────────────────────────────────────────────────────────

export function useGpsBoundary() {
  let geojsonCache = null

  async function loadGeojson(url) {
    if (geojsonCache) return geojsonCache
    try {
      const res = await fetch(url)
      if (res.ok) geojsonCache = await res.json()
    } catch {}
    return geojsonCache
  }

  /**
   * Returns null if check cannot run, or:
   *   { status: 'inside'|'near'|'warn'|'far', distanceM: number }
   *
   * Tiers:
   *   inside  — 0 m inside boundary
   *   near    — 1–150 m outside  (likely GPS accuracy drift)
   *   warn    — 151–500 m outside (verify location)
   *   far     — >500 m outside   (probable wrong ward)
   */
  async function checkBoundary(lat, lng, wardNumber, geojsonUrl, wardProp = 'WARD_NO') {
    if (!lat || !lng || !wardNumber || !geojsonUrl) return null

    const geojson = await loadGeojson(geojsonUrl)
    if (!geojson?.features) return null

    const feature = geojson.features.find(
      f => String(f.properties?.[wardProp]) === String(wardNumber)
    )
    if (!feature?.geometry) return null

    const geomType = feature.geometry.type
    const rings = geomType === 'MultiPolygon'
      ? feature.geometry.coordinates.map(poly => poly[0])
      : [feature.geometry.coordinates[0]]

    if (rings.some(ring => pointInRing(lat, lng, ring))) {
      return { status: 'inside', distanceM: 0 }
    }

    const distM = Math.round(Math.min(...rings.map(ring => distToRingM(lat, lng, ring))))

    let status
    if (distM <= 150)      status = 'near'
    else if (distM <= 500) status = 'warn'
    else                   status = 'far'

    return { status, distanceM: distM }
  }

  return { checkBoundary }
}
