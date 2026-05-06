<template>
  <div class="flex flex-col" style="height: calc(100vh - 56px);">

    <!-- ── Toolbar ── -->
    <div class="bg-white border-b border-[#e6e7e9] px-4 sm:px-6 py-3 flex flex-wrap items-center gap-3 shrink-0">

      <!-- Ward filter -->
      <div class="flex items-center gap-2">
        <label class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] shrink-0 whitespace-nowrap">
          {{ t('filter.ward') }}
        </label>
        <select v-model="selWard" @change="onWardChange"
          class="rounded border border-[#e6e7e9] bg-white px-3 py-1.5 text-[13px] leading-normal
                 text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]
                 transition outline-none min-w-[180px]">
          <option value="">{{ t('filter.allWards') }}</option>
          <option v-for="w in props.wards" :key="w.id" :value="String(w.id)">
            {{ t('common.ward') }} {{ w.ward_number }}{{ props.wardCounts[w.id] ? ` (${props.wardCounts[w.id]})` : '' }}
          </option>
        </select>
      </div>

      <!-- Point count badge -->
      <div class="flex items-center gap-1.5 text-[12px] text-[#626976]">
        <i class="fa-solid fa-map-pin text-[#f59f00] text-[11px]"></i>
        <span class="font-semibold text-[#1d273b]">{{ props.totalPoints.toLocaleString() }}</span>
        {{ t('map.points') }}
      </div>

      <!-- Cluster toggle -->
      <label class="flex items-center gap-1.5 text-[12px] text-[#626976] cursor-pointer ml-auto">
        <input type="checkbox" v-model="cluster" class="rounded">
        {{ t('map.cluster') }}
      </label>

      <!-- Fit bounds button -->
      <button @click="fitBounds"
        class="flex items-center gap-1.5 px-3 py-1.5 border border-[#e6e7e9] rounded
               text-[12px] text-[#626976] hover:bg-gray-50 transition">
        <i class="fa-solid fa-compress text-[10px]"></i>
        {{ t('map.fitAll') }}
      </button>
    </div>

    <!-- ── Map container ── -->
    <div ref="mapEl" class="flex-1 w-full z-0"></div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { router }    from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'
import PublicLayout  from '@/layouts/PublicLayout.vue'

function toRawGithubUrl(url) {
  if (!url) return url
  // Convert GitHub blob URL to raw content URL automatically
  return url.replace(
    /^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/(.+)$/,
    'https://raw.githubusercontent.com/$1/$2/$3'
  )
}

defineOptions({ layout: PublicLayout })

const { t } = useLocale()

const props = defineProps({
  lg:          { type: Object, default: null },
  wards:       { type: Array,  default: () => [] },
  points:      { type: Array,  default: () => [] },
  wardCounts:  { type: Object, default: () => ({}) },
  filters:     { type: Object, default: () => ({}) },
  totalPoints: { type: Number, default: 0 },
})

// ── State ──────────────────────────────────────────────────────────────────
const mapEl   = ref(null)
const selWard = ref(String(props.filters.ward_id ?? ''))
const cluster = ref(true)

let L        = null   // Leaflet
let map      = null
let markers  = []     // raw Leaflet marker list
let mcGroup  = null   // MarkerClusterGroup (optional)

// ── Ward filter navigation ─────────────────────────────────────────────────
function onWardChange() {
  const params = {}
  if (selWard.value) params.ward_id = selWard.value
  router.get('/public/map', params, { preserveScroll: false, preserveState: false })
}

// ── Map helpers ─────────────────────────────────────────────────────────────
function markerColor(ward) {
  // Cycle through palette by ward number
  const colors = ['#206bc4','#0ca678','#f59f00','#d63939','#ae3ec9',
                  '#4299e1','#2fb344','#f76707','#6366f1','#e64980']
  return colors[(ward - 1) % colors.length]
}

function makeSvgIcon(L, color) {
  return L.divIcon({
    className: '',
    iconSize:   [12, 12],
    iconAnchor: [6, 6],
    popupAnchor:[0, -8],
    html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" width="12" height="12">
      <circle cx="6" cy="6" r="5" fill="${color}" stroke="white" stroke-width="1.5"/>
    </svg>`,
  })
}

function buildPopup(pt) {
  return `
    <div style="font-family:sans-serif;min-width:160px;font-size:13px;">
      <div style="font-weight:700;color:#1d273b;margin-bottom:4px;">
        ${pt.code ?? 'घर'}
      </div>
      <table style="width:100%;border-collapse:collapse;">
        <tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">वडा</td>
            <td style="font-weight:600;">वडा ${pt.ward}</td></tr>
        ${pt.name ? `<tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">उत्तरदाता</td><td>${pt.name}</td></tr>` : ''}
        ${pt.alt ? `<tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">उचाइ</td><td>${pt.alt} m</td></tr>` : ''}
        <tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">GPS</td>
            <td style="font-size:11px;color:#626976;">${pt.lat.toFixed(5)}, ${pt.lng.toFixed(5)}</td></tr>
      </table>
    </div>`
}

async function initMap() {
  if (!mapEl.value) return

  // Dynamic import to avoid SSR issues
  L = (await import('leaflet')).default
  await import('leaflet/dist/leaflet.css')

  const cfg        = props.lg?.map_config ?? {}
  const defLat     = cfg.center_lat ?? 27.7
  const defLng     = cfg.center_lng ?? 85.3

  map = L.map(mapEl.value, {
    center: [props.points.length ? props.points[0].lat : defLat, props.points.length ? props.points[0].lng : defLng],
    zoom: cfg.zoom ?? 12,
    zoomControl: true,
  })

  // OpenStreetMap tile layer
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map)
  const geojsonUrl = toRawGithubUrl(cfg.geojson_url)
  const wardProp   = cfg.ward_number_property ?? 'WARD_NO'

  if (geojsonUrl) {
    // Build ward_number → GPS point count lookup
    const wardNumToCount = Object.fromEntries(
      props.wards.map(w => [String(w.ward_number), props.wardCounts[w.id] ?? 0])
    )
    const maxCount = Math.max(...Object.values(wardNumToCount), 1)

    const choroplethColor = cnt => {
      const r = cnt / maxCount
      if (r > 0.8) return '#1a4e8a'
      if (r > 0.6) return '#2169b8'
      if (r > 0.4) return '#4299e1'
      if (r > 0.2) return '#90cdf4'
      if (cnt > 0) return '#bee3f8'
      return '#e8f0fb'
    }

    try {
      const res = await fetch(geojsonUrl)
      if (res.ok) {
        const geojson = await res.json()
        L.geoJSON(geojson, {
          style: feature => {
            const wNum = String(feature.properties?.[wardProp] ?? '')
            return {
              fillColor:   choroplethColor(wardNumToCount[wNum] ?? 0),
              weight:      1.5,
              opacity:     1,
              color:       '#ffffff',
              fillOpacity: 0.55,
            }
          },
          onEachFeature: (feature, layer) => {
            const wNum  = String(feature.properties?.[wardProp] ?? '')
            const count = wardNumToCount[wNum] ?? 0
            layer.bindPopup(`<b>वडा नं. ${wNum}</b><br>GPS घरधुरी: ${count.toLocaleString()}`)
            layer.on({
              mouseover: e => { e.target.setStyle({ weight: 2.5, fillOpacity: 0.8 }); e.target.bringToFront() },
              mouseout:  e => { e.target.setStyle({ weight: 1.5, fillOpacity: 0.55 }) },
            })
          },
        }).addTo(map)

        const legend = L.control({ position: 'bottomright' })
        legend.onAdd = () => {
          const div = L.DomUtil.create('div')
          div.style.cssText = 'background:rgba(255,255,255,0.92);padding:8px 10px;border-radius:6px;font-size:11px;line-height:1.8;box-shadow:0 1px 5px rgba(0,0,0,0.18)'
          div.innerHTML =
            '<b style="display:block;margin-bottom:2px">GPS घरधुरी</b>' +
            [['#1a4e8a','80–100%'],['#2169b8','60–80%'],['#4299e1','40–60%'],['#90cdf4','20–40%'],['#bee3f8','0–20%']]
              .map(([c,l]) => `<span style="display:inline-block;width:11px;height:11px;background:${c};margin-right:5px;border-radius:2px;vertical-align:middle"></span>${l}`)
              .join('<br>')
          return div
        }
        legend.addTo(map)
      }
    } catch {
      // GeoJSON unavailable — markers still show without choropleth
    }
  }

  renderMarkers()
  fitBounds()
}

function renderMarkers() {
  if (!map || !L) return

  // Remove old markers / cluster group
  if (mcGroup) { map.removeLayer(mcGroup); mcGroup = null }
  markers.forEach(m => map.removeLayer(m))
  markers = []

  if (!props.points.length) return

  // Try to load MarkerCluster if cluster mode on
  const addTarget = (() => {
    try {
      if (cluster.value) {
        // eslint-disable-next-line no-undef
        mcGroup = L.markerClusterGroup({ maxClusterRadius: 40, showCoverageOnHover: false })
        map.addLayer(mcGroup)
        return mcGroup
      }
    } catch (_) {}
    return map
  })()

  props.points.forEach(pt => {
    const icon   = makeSvgIcon(L, markerColor(pt.ward))
    const marker = L.marker([pt.lat, pt.lng], { icon })
    marker.bindPopup(buildPopup(pt), { maxWidth: 240 })
    addTarget.addLayer ? addTarget.addLayer(marker) : marker.addTo(addTarget)
    markers.push(marker)
  })
}

function fitBounds() {
  if (!map || !props.points.length) return
  const bounds = props.points.map(p => [p.lat, p.lng])
  map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 })
}

// Re-render markers when cluster toggle changes
watch(cluster, () => {
  if (map) renderMarkers()
})

onMounted(async () => {
  await nextTick()
  await initMap()
})

onUnmounted(() => {
  if (map) { map.remove(); map = null }
})
</script>
