/**
 * Generates Nepali narrative sentences from ward / municipality data.
 * Pure JS — no external dependencies.
 */

// ── Ward-level narrative (used on WardProfile) ───────────────────────────────
export function wardNarrative(wardNum, overview, economic) {
  if (!overview || !overview.households) return null

  const hh  = overview.households
  const pop = overview.population
  const male   = overview.male   ?? 0
  const female = overview.female ?? 0
  const lit  = overview.literacy_pct ?? 0
  const bank = economic?.bank_pct ?? 0

  const lines = []

  // Sentence 1 — population & gender
  lines.push(
    `वडा ${wardNum} मा ${hh.toLocaleString()} घरधुरी र ${pop.toLocaleString()} जनसंख्या रहेको छ ` +
    `(पुरुष ${male.toLocaleString()}, महिला ${female.toLocaleString()})।`
  )

  // Sentence 2 — literacy & bank
  const litLevel  = lit  >= 80 ? 'उच्च' : lit  >= 60 ? 'मध्यम' : 'न्यून'
  const bankLevel = bank >= 70 ? 'राम्रो' : bank >= 50 ? 'मध्यम' : 'न्यून'
  lines.push(
    `साक्षरता दर ${lit}% (${litLevel}) र ${bank}% घरधुरीमा बैंक खाता (${bankLevel} पहुँच) उपलब्ध छ।`
  )

  // Sentence 3 — institutions & businesses (only if non-zero)
  const inst = overview.institutions ?? 0
  const biz  = overview.businesses   ?? 0
  if (inst > 0 || biz > 0) {
    const parts = []
    if (inst > 0) parts.push(`${inst} वटा संस्था`)
    if (biz  > 0) parts.push(`${biz} वटा व्यवसाय`)
    lines.push(`यस वडामा ${parts.join(' र ')} दर्ता छन्।`)
  }

  return lines.join(' ')
}

// ── Municipality-level narrative (used on WardSummary) ───────────────────────
export function municipalityNarrative(lgNameNp, wardSummary) {
  if (!wardSummary || !wardSummary.length) return null

  const totalHH  = wardSummary.reduce((s, w) => s + w.households, 0)
  const totalPop = wardSummary.reduce((s, w) => s + (w.total ?? 0), 0)

  // Average literacy across wards (weighted)
  const litNum = wardSummary.reduce((s, w) => s + (w.literate       ?? 0), 0)
  const litDen = wardSummary.reduce((s, w) => s + (w.literate_total ?? 0), 0)
  const avgLit = litDen > 0 ? Math.round(litNum / litDen * 100) : 0

  // Average safe water
  const wNum = wardSummary.reduce((s, w) => s + (w.safe_water  ?? 0), 0)
  const wDen = wardSummary.reduce((s, w) => s + (w.utils_total ?? 0), 0)
  const avgWater = wDen > 0 ? Math.round(wNum / wDen * 100) : 0

  // Best/worst ward by literacy
  const sorted = [...wardSummary]
    .filter(w => (w.literate_total ?? 0) > 0)
    .sort((a, b) => {
      const pctA = a.literate_total > 0 ? a.literate / a.literate_total : 0
      const pctB = b.literate_total > 0 ? b.literate / b.literate_total : 0
      return pctB - pctA
    })
  const bestWard  = sorted[0]
  const worstWard = sorted[sorted.length - 1]

  const lines = []

  lines.push(
    `${lgNameNp} मा जम्मा ${totalHH.toLocaleString()} घरधुरी र ${totalPop.toLocaleString()} जनसंख्या प्रकाशित तथ्याङ्क अनुसार रहेको छ।`
  )

  lines.push(
    `औसत साक्षरता दर ${avgLit}% र ${avgWater}% घरधुरीमा सुरक्षित खानेपानी उपलब्ध छ।`
  )

  if (bestWard && worstWard && bestWard.ward_number !== worstWard.ward_number) {
    const bestPct  = Math.round(bestWard.literate  / bestWard.literate_total  * 100)
    const worstPct = Math.round(worstWard.literate / worstWard.literate_total * 100)
    lines.push(
      `साक्षरतामा सबैभन्दा अग्रणी वडा ${bestWard.ward_number} (${bestPct}%) र ` +
      `थप सुधार आवश्यक वडा ${worstWard.ward_number} (${worstPct}%) छ।`
    )
  }

  return lines.join(' ')
}
