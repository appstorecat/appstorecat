import type { ChangeResource } from '@/api/models/changeResource'
import type { ChangeGroup } from './ChangeGroupCard'

/**
 * Bucket rows under the same detection day + app + version transition so
 * the UI can show "SMS io, yesterday, v1.2.6 → v1.2.7" as one card instead
 * of a wall of per-locale rows.
 */
export function groupChanges(rows: ChangeResource[]): ChangeGroup[] {
  const buckets = new Map<string, ChangeGroup>()

  for (const row of rows) {
    if (!row.app) continue

    const detectedAt = row.detected_at ?? ''
    const dayKey = detectedAt.slice(0, 10) // YYYY-MM-DD
    const prev = row.previous_version ?? ''
    const next = row.version ?? ''
    const key = `${dayKey}|${row.app.id}|${prev}|${next}`

    const existing = buckets.get(key)
    if (existing) {
      existing.rows.push(row)
      if (detectedAt < existing.detectedAt) existing.detectedAt = detectedAt
      continue
    }

    buckets.set(key, {
      key,
      app: row.app,
      previousVersion: row.previous_version ?? null,
      version: row.version ?? null,
      detectedAt: detectedAt || new Date().toISOString(),
      rows: [row],
    })
  }

  // Newest first — order matches the server's `detected_at DESC`.
  return Array.from(buckets.values()).sort((a, b) =>
    b.detectedAt.localeCompare(a.detectedAt),
  )
}

export type DateSection = 'today' | 'yesterday' | 'last_7' | 'earlier'

export interface DateSectionBucket {
  section: DateSection
  label: string
  groups: ChangeGroup[]
}

const SECTION_LABELS: Record<DateSection, string> = {
  today: 'Today',
  yesterday: 'Yesterday',
  last_7: 'Last 7 days',
  earlier: 'Earlier',
}

function daysBetween(a: Date, b: Date): number {
  const ms = a.getTime() - b.getTime()
  return Math.floor(ms / (1000 * 60 * 60 * 24))
}

export function sectionForDate(dateStr: string): DateSection {
  const now = new Date()
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const parsed = new Date(dateStr)
  if (Number.isNaN(parsed.getTime())) return 'earlier'
  const that = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate())
  const delta = daysBetween(today, that)
  if (delta <= 0) return 'today'
  if (delta === 1) return 'yesterday'
  if (delta <= 7) return 'last_7'
  return 'earlier'
}

export function bucketByDateSection(groups: ChangeGroup[]): DateSectionBucket[] {
  const order: DateSection[] = ['today', 'yesterday', 'last_7', 'earlier']
  const map = new Map<DateSection, ChangeGroup[]>()
  for (const g of groups) {
    const section = sectionForDate(g.detectedAt)
    const arr = map.get(section) ?? []
    arr.push(g)
    map.set(section, arr)
  }
  return order
    .filter((s) => (map.get(s)?.length ?? 0) > 0)
    .map((s) => ({
      section: s,
      label: SECTION_LABELS[s],
      groups: map.get(s) ?? [],
    }))
}
