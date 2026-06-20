<template>
  <div class="flex flex-col" style="height: calc(100dvh - 56px);">

    <!-- ── Toolbar ── -->
    <div class="bg-white border-b border-[#e6e7e9] px-4 sm:px-6 py-3 flex flex-wrap items-center gap-3 shrink-0">

      <!-- Ward filter -->
      <div class="flex items-center gap-2">
        <label class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] shrink-0 whitespace-nowrap">
          {{ t('filter.ward') }}
        </label>
        <select v-model="selWard" @change="applyFilters"
          class="rounded border border-[#e6e7e9] bg-white px-3 py-1.5 text-[13px] leading-normal
                 text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]
                 transition outline-none min-w-[140px]">
          <option value="">{{ t('filter.allWards') }}</option>
          <option v-for="w in props.wards" :key="w.id" :value="String(w.id)">
            {{ t('common.ward') }} {{ w.ward_number }}{{ props.wardCounts[w.id] ? ` (${props.wardCounts[w.id]})` : '' }}
          </option>
        </select>
      </div>

      <!-- Type filter -->
      <div class="flex items-center gap-2">
        <label class="text-[11px] font-semibold uppercase tracking-[0.12em] text-[#9aa0ac] shrink-0 whitespace-nowrap">
          {{ locale === 'ne' ? 'प्रकार' : 'Type' }}
        </label>
        <select v-model="selType" @change="applyFilters"
          class="rounded border border-[#e6e7e9] bg-white px-3 py-1.5 text-[13px] leading-normal
                 text-[#1d273b] focus:ring-2 focus:ring-[#206bc4]/25 focus:border-[#206bc4]
                 transition outline-none min-w-[180px]">
          <option value="">{{ locale === 'ne' ? '— सबै प्रकार —' : '— All Types —' }}</option>
          <option v-for="tp in props.types" :key="tp.id" :value="String(tp.id)">
            {{ locale === 'ne' ? tp.name_np : tp.name_en }}
          </option>
        </select>
      </div>

      <!-- Point count badge -->
      <div class="flex items-center gap-1.5 text-[12px] text-[#626976]">
        <i class="fa-solid fa-map-pin text-[#e64980] text-[11px]"></i>
        <span class="font-semibold text-[#1d273b]">{{ props.totalPoints.toLocaleString() }}</span>
        {{ locale === 'ne' ? 'संस्थाहरू' : 'institutions' }}
      </div>

      <div class="ml-auto flex items-center gap-2">
        <!-- Cluster toggle -->
        <label class="flex items-center gap-1.5 text-[12px] text-[#626976] cursor-pointer">
          <input type="checkbox" v-model="cluster" class="rounded">
          {{ t('map.cluster') }}
        </label>

        <!-- Fit bounds -->
        <button @click="fitBounds"
          class="flex items-center gap-1.5 px-3 py-1.5 border border-[#e6e7e9] rounded
                 text-[12px] text-[#626976] hover:bg-gray-50 transition">
          <i class="fa-solid fa-compress text-[10px]"></i>
          {{ t('map.fitAll') }}
        </button>
      </div>
    </div>

    <!-- ── Map + Legend wrapper ── -->
    <div class="flex-1 relative">
      <!-- Map -->
      <div ref="mapEl" class="absolute inset-0 z-0" aria-label="संस्था नक्सा" role="img"></div>

      <!-- Tile / network error overlay -->
      <div v-if="mapError"
        class="absolute inset-0 z-[2000] flex flex-col items-center justify-center bg-white/90 backdrop-blur-sm">
        <div class="w-16 h-16 rounded-2xl bg-red-50 flex items-center justify-center mb-4">
          <i class="fa-solid fa-triangle-exclamation text-red-400 text-2xl"></i>
        </div>
        <p class="font-semibold text-[#1d273b] mb-1">नक्सा लोड भएन</p>
        <p class="text-sm text-gray-400 text-center max-w-xs">{{ mapError }}</p>
        <button @click="retryMap"
          class="mt-4 px-4 py-2 rounded-lg bg-[#206bc4] text-white text-sm font-medium hover:bg-[#1a5ba8] transition">
          पुनः प्रयास गर्नुहोस्
        </button>
      </div>

      <!-- GeoJSON boundary notice (non-blocking) -->
      <div v-if="geojsonFailed"
        class="absolute top-3 left-1/2 -translate-x-1/2 z-[1500] bg-amber-50 border border-amber-200
               rounded-lg px-3 py-1.5 text-[12px] text-amber-700 flex items-center gap-2 shadow-sm whitespace-nowrap">
        <i class="fa-solid fa-circle-info text-amber-500 text-[10px]"></i>
        नक्सा सिमाना लोड भएन — मार्कर उपलब्ध छन्
        <button @click="geojsonFailed = false" class="ml-1 text-amber-400 hover:text-amber-600">
          <i class="fa-solid fa-xmark text-[10px]"></i>
        </button>
      </div>

      <!-- Empty state — no points after filter -->
      <div v-if="!mapError && !props.totalPoints"
        class="absolute inset-0 z-[1000] flex flex-col items-center justify-center pointer-events-none">
        <div class="bg-white/95 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-100 px-8 py-6 text-center max-w-xs">
          <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-building-circle-xmark text-gray-400 text-xl"></i>
          </div>
          <p class="font-semibold text-[#1d273b] mb-1">कुनै संस्था फेला परेन</p>
          <p class="text-xs text-gray-400 leading-relaxed">
            {{ selWard || selType ? 'यस फिल्टरमा कुनै GPS संस्था छैन — फिल्टर हटाउनुहोस्।' : 'GPS सहित दर्ता भएको कुनै संस्था छैन।' }}
          </p>
        </div>
      </div>

      <!-- ── Legend — bottom-right overlay ── -->
      <div v-if="Object.keys(visibleTypeColors).length"
        class="absolute bottom-6 right-3 z-[1000] bg-white border border-[#e6e7e9] rounded-lg shadow-lg
               min-w-[160px] max-w-[220px] overflow-hidden">
        <div class="px-3 py-2 border-b border-[#e6e7e9] bg-gray-50">
          <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-[#626976]">
            {{ locale === 'ne' ? 'संकेत' : 'Legend' }}
          </p>
        </div>
        <ul class="px-3 py-2 space-y-1.5 max-h-[240px] overflow-y-auto">
          <li v-for="(color, typeId) in visibleTypeColors" :key="typeId"
            class="flex items-center gap-2 text-[12px] text-[#1d273b]">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="13" height="13" class="shrink-0">
              <rect x="1" y="1" width="12" height="12" rx="2" :fill="color" stroke="white" stroke-width="1.5"/>
            </svg>
            <span class="leading-tight">{{ typeLabels[typeId] ?? typeId }}</span>
          </li>
        </ul>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import { useLocale } from '@/i18n/index.js'
import PublicLayout from '@/layouts/PublicLayout.vue'

defineOptions({ layout: PublicLayout })

function toRawGithubUrl(url) {
  if (!url) return url
  url = url.replace(
    /^https?:\/\/github\.com\/([^/]+)\/([^/]+)\/blob\/(.+)$/,
    'https://raw.githubusercontent.com/$1/$2/$3'
  )
  url = url.replace(/^http:\/\//, 'https://')
  return url
}

const { t, locale } = useLocale()

const props = defineProps({
  lg:          { type: Object, default: null },
  wards:       { type: Array,  default: () => [] },
  types:       { type: Array,  default: () => [] },
  points:      { type: Array,  default: () => [] },
  wardCounts:  { type: Object, default: () => ({}) },
  filters:     { type: Object, default: () => ({}) },
  totalPoints: { type: Number, default: 0 },
})

// ── State ──────────────────────────────────────────────────────────────────
const mapEl       = ref(null)
const selWard     = ref(String(props.filters.ward_id ?? ''))
const selType     = ref(String(props.filters.type_id ?? ''))
const cluster     = ref(true)
const mapError    = ref('')
const geojsonFailed = ref(false)

let L       = null
let map     = null
let markers = []
let mcGroup = null

// ── Colour palette — one per type id ──────────────────────────────────────
const PALETTE = [
  '#206bc4','#0ca678','#f59f00','#d63939','#ae3ec9',
  '#4299e1','#2fb344','#f76707','#6366f1','#e64980',
  '#17a2b8','#fd7e14','#6610f2','#20c997','#dc3545',
]

const typeColorMap = computed(() => {
  const m = {}
  props.types.forEach((tp, i) => { m[tp.id] = PALETTE[i % PALETTE.length] })
  return m
})

const typeLabels = computed(() => {
  const m = {}
  props.types.forEach(tp => { m[tp.id] = locale.value === 'ne' ? tp.name_np : tp.name_en })
  return m
})

// Only types present in current filtered points (coerce to string for safe Set lookup)
const visibleTypeColors = computed(() => {
  const seen = new Set(props.points.map(p => String(p.type_id)))
  const result = {}
  props.types.forEach(tp => {
    if (seen.has(String(tp.id))) result[tp.id] = typeColorMap.value[tp.id]
  })
  return result
})

function retryMap() {
  mapError.value = ''
  geojsonFailed.value = false
  if (map) { map.remove(); map = null; markers = []; mcGroup = null }
  initMap()
}

// ── Navigation ─────────────────────────────────────────────────────────────
function applyFilters() {
  const params = {}
  if (selWard.value) params.ward_id = selWard.value
  if (selType.value) params.type_id = selType.value
  router.get('/public/institution-map', params, { preserveScroll: false, preserveState: false })
}

// ── Map helpers ─────────────────────────────────────────────────────────────
function makeSvgIcon(color) {
  return L.divIcon({
    className: '',
    iconSize:   [14, 14],
    iconAnchor: [7, 7],
    popupAnchor:[0, -10],
    html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 14 14" width="14" height="14">
      <rect x="1" y="1" width="12" height="12" rx="2" fill="${color}" stroke="white" stroke-width="1.5"/>
    </svg>`,
  })
}

function buildPopup(pt) {
  const name = locale.value === 'ne' ? (pt.name_np || pt.name_en) : (pt.name_en || pt.name_np)
  const type = locale.value === 'ne' ? pt.type_np : pt.type_en
  return `
    <div style="font-family:sans-serif;min-width:180px;font-size:13px;">
      <div style="font-weight:700;color:#1d273b;margin-bottom:2px;">${name ?? pt.code}</div>
      <div style="font-size:11px;color:#9aa0ac;margin-bottom:6px;">${type ?? ''}</div>
      <table style="width:100%;border-collapse:collapse;">
        <tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;white-space:nowrap;">कोड</td>
            <td style="font-weight:600;">${pt.code}</td></tr>
        <tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;white-space:nowrap;">वडा</td>
            <td style="font-weight:600;">वडा ${pt.ward}</td></tr>
        ${pt.alt ? `<tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">उचाइ</td><td>${pt.alt} m</td></tr>` : ''}
        <tr><td style="color:#9aa0ac;padding:1px 6px 1px 0;">GPS</td>
            <td style="font-size:11px;color:#626976;">${pt.lat.toFixed(5)}, ${pt.lng.toFixed(5)}</td></tr>
      </table>
    </div>`
}

async function initMap() {
  if (!mapEl.value) return
  L = (await import('leaflet')).default
  await import('leaflet/dist/leaflet.css')

  const cfg    = props.lg?.map_config ?? {}
  const defLat = cfg.center_lat ?? 27.7
  const defLng = cfg.center_lng ?? 85.3

  map = L.map(mapEl.value, {
    center: [props.points.length ? props.points[0].lat : defLat,
             props.points.length ? props.points[0].lng : defLng],
    zoom: cfg.zoom ?? 12,
    zoomControl: true,
  })

  const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    maxZoom: 19,
    errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  })
  tileLayer.on('tileerror', () => {
    if (!mapError.value) mapError.value = 'इन्टरनेट कनेक्सन वा नक्सा सर्भर उपलब्ध छैन।'
  })
  tileLayer.addTo(map)

  const geojsonUrl = toRawGithubUrl(cfg.geojson_url)
  const wardProp   = cfg.ward_number_property ?? 'WARD_NO'

  if (geojsonUrl) {
    const wardNumToCount = Object.fromEntries(
      props.wards.map(w => [String(w.ward_number), props.wardCounts[w.id] ?? 0])
    )
    const maxCount = Math.max(...Object.values(wardNumToCount), 1)

    const choroplethColor = cnt => {
      const r = cnt / maxCount
      if (r > 0.8) return '#4f46e5'
      if (r > 0.6) return '#6366f1'
      if (r > 0.4) return '#818cf8'
      if (r > 0.2) return '#a5b4fc'
      if (cnt > 0) return '#c7d2fe'
      return '#eef2ff'
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
              fillOpacity: 0.45,
            }
          },
          onEachFeature: (feature, layer) => {
            const wNum  = String(feature.properties?.[wardProp] ?? '')
            const count = wardNumToCount[wNum] ?? 0
            layer.bindPopup(`<b>वडा नं. ${wNum}</b><br>GPS संस्थाहरू: ${count.toLocaleString()}`)
            layer.on({
              mouseover: e => { e.target.setStyle({ weight: 2.5, fillOpacity: 0.75 }); e.target.bringToFront() },
              mouseout:  e => { e.target.setStyle({ weight: 1.5, fillOpacity: 0.45 }) },
            })
          },
        }).addTo(map)

        const legend = L.control({ position: 'bottomleft' })
        legend.onAdd = () => {
          const div = L.DomUtil.create('div')
          div.style.cssText = 'background:rgba(255,255,255,0.92);padding:8px 10px;border-radius:6px;font-size:11px;line-height:1.8;box-shadow:0 1px 5px rgba(0,0,0,0.18)'
          div.innerHTML =
            '<b style="display:block;margin-bottom:2px">GPS संस्थाहरू</b>' +
            [['#4f46e5','80–100%'],['#6366f1','60–80%'],['#818cf8','40–60%'],['#a5b4fc','20–40%'],['#c7d2fe','0–20%']]
              .map(([c, l]) => `<span style="display:inline-block;width:11px;height:11px;background:${c};margin-right:5px;border-radius:2px;vertical-align:middle"></span>${l}`)
              .join('<br>')
          return div
        }
        legend.addTo(map)
      }
    } catch {
      geojsonFailed.value = true
    }
  }

  renderMarkers()
  fitBounds()
}

function renderMarkers() {
  if (!map || !L) return

  if (mcGroup) { map.removeLayer(mcGroup); mcGroup = null }
  markers.forEach(m => map.removeLayer(m))
  markers = []

  if (!props.points.length) return

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
    const color  = typeColorMap.value[pt.type_id] ?? '#e64980'
    const icon   = makeSvgIcon(color)
    const marker = L.marker([pt.lat, pt.lng], { icon })
    marker.bindPopup(buildPopup(pt), { maxWidth: 260 })
    addTarget.addLayer ? addTarget.addLayer(marker) : marker.addTo(addTarget)
    markers.push(marker)
  })
}

function fitBounds() {
  if (!map || !props.points.length) return
  map.fitBounds(props.points.map(p => [p.lat, p.lng]), { padding: [40, 40], maxZoom: 15 })
}

watch(cluster, () => { if (map) renderMarkers() })

onMounted(async () => {
  await nextTick()
  await initMap()
})

onUnmounted(() => {
  if (map) { map.remove(); map = null }
})
</script>
