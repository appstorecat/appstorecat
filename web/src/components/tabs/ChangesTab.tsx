import { useMemo, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  History,
  RefreshCw,
  ArrowDown,
  ArrowUp,
  Sparkles,
  ChevronRight,
  Globe,
} from 'lucide-react'
import ChangeFieldRow from '@/components/changes/ChangeFieldRow'
import type { ListingResource } from '@/api/models/listingResource'
import type { VersionResource } from '@/api/models/versionResource'

interface ChangesTabProps {
  listings: ListingResource[]
  versions: VersionResource[]
}

type TextField = 'title' | 'subtitle' | 'description' | 'whats_new'
type ChangeKind = TextField | 'file_size'

interface LocaleDiff {
  locale: string
  field: TextField | 'locale_added' | 'locale_removed'
  oldValue: string | null
  newValue: string | null
}

interface TimelineEntry {
  version: VersionResource
  previousVersion: VersionResource | null
  isInitialRelease: boolean
  localesCount: number
  releaseNotesByLocale: Record<string, string | null>
  availableLocales: string[]
  localeDiffs: LocaleDiff[]
  fileSizeDelta: { direction: 'increased' | 'decreased'; bytes: number } | null
  summary: {
    title: number
    subtitle: number
    description: number
    whats_new: number
    file_size: boolean
  }
}

const TEXT_FIELDS: TextField[] = ['title', 'subtitle', 'description', 'whats_new']

const FIELD_LABELS: Record<ChangeKind, string> = {
  title: 'Name',
  subtitle: 'Subtitle',
  description: 'Description',
  whats_new: "What's New",
  file_size: 'File size',
}

const FILTER_OPTIONS: { kind: ChangeKind; label: string }[] = [
  { kind: 'title', label: 'Name changes' },
  { kind: 'subtitle', label: 'Subtitle changes' },
  { kind: 'description', label: 'Description changes' },
  { kind: 'whats_new', label: "What's New changes" },
  { kind: 'file_size', label: 'File size changes' },
]

function formatBytes(bytes: number): string {
  const mb = bytes / (1024 * 1024)
  return `${mb.toFixed(1)} MB`
}

function pickDefaultLocale(locales: string[]): string {
  if (locales.includes('en')) return 'en'
  if (locales.includes('en-US')) return 'en-US'
  const enUsCi = locales.find((l) => l.toLowerCase() === 'en-us' || l.toLowerCase() === 'en_us')
  if (enUsCi) return enUsCi
  const enVariant = locales.find((l) => l.toLowerCase().startsWith('en'))
  if (enVariant) return enVariant
  return locales[0] ?? ''
}

function formatRelativeDate(dateStr: string | null): string | null {
  if (!dateStr) return null
  const date = new Date(dateStr)
  if (Number.isNaN(date.getTime())) return null

  const diffMs = Date.now() - date.getTime()
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

  if (diffDays < 1) return 'today'
  if (diffDays === 1) return 'yesterday'
  if (diffDays < 30) return `${diffDays} days ago`
  const months = Math.floor(diffDays / 30)
  if (months < 12) return `${months} month${months !== 1 ? 's' : ''} ago`
  const years = Math.floor(diffDays / 365)
  return `${years} year${years !== 1 ? 's' : ''} ago`
}

function computeLocaleDiffs(
  oldListings: ListingResource[],
  newListings: ListingResource[],
): LocaleDiff[] {
  const diffs: LocaleDiff[] = []
  const oldByLocale = new Map(oldListings.map((l) => [l.locale, l]))
  const newByLocale = new Map(newListings.map((l) => [l.locale, l]))
  const allLocales = new Set([...oldByLocale.keys(), ...newByLocale.keys()])

  for (const loc of allLocales) {
    const oldL = oldByLocale.get(loc)
    const newL = newByLocale.get(loc)

    if (!oldL && newL) {
      diffs.push({ locale: loc, field: 'locale_added', oldValue: null, newValue: newL.title ?? null })
      continue
    }
    if (oldL && !newL) {
      diffs.push({ locale: loc, field: 'locale_removed', oldValue: oldL.title ?? null, newValue: null })
      continue
    }
    if (oldL && newL) {
      for (const f of TEXT_FIELDS) {
        const ov = oldL[f] ?? null
        const nv = newL[f] ?? null
        if (ov !== nv) {
          diffs.push({ locale: loc, field: f, oldValue: ov, newValue: nv })
        }
      }
    }
  }
  return diffs
}

function buildTimeline(
  listings: ListingResource[],
  versions: VersionResource[],
): TimelineEntry[] {
  // Backend returns versions newest-first (App::versions() relation orders by id desc).
  const sorted = versions
  const listingsByVersion = new Map<number, ListingResource[]>()
  for (const l of listings) {
    if (l.version_id == null) continue
    if (!listingsByVersion.has(l.version_id)) listingsByVersion.set(l.version_id, [])
    listingsByVersion.get(l.version_id)!.push(l)
  }

  return sorted.map((version, idx) => {
    const previousVersion = sorted[idx + 1] ?? null
    const currListings = listingsByVersion.get(version.id) ?? []
    const prevListings = previousVersion
      ? listingsByVersion.get(previousVersion.id) ?? []
      : []

    const releaseNotesByLocale: Record<string, string | null> = {}
    for (const l of currListings) {
      releaseNotesByLocale[l.locale] = l.whats_new ?? null
    }
    const availableLocales = Object.keys(releaseNotesByLocale).sort()

    const localeDiffs = previousVersion
      ? computeLocaleDiffs(prevListings, currListings)
      : []

    let fileSizeDelta: TimelineEntry['fileSizeDelta'] = null
    if (
      previousVersion &&
      version.file_size_bytes != null &&
      previousVersion.file_size_bytes != null &&
      version.file_size_bytes !== previousVersion.file_size_bytes
    ) {
      const delta = version.file_size_bytes - previousVersion.file_size_bytes
      fileSizeDelta = {
        direction: delta > 0 ? 'increased' : 'decreased',
        bytes: Math.abs(delta),
      }
    }

    const summary = {
      title: 0,
      subtitle: 0,
      description: 0,
      whats_new: 0,
      file_size: fileSizeDelta !== null,
    }
    const seenLocalesByField: Record<TextField, Set<string>> = {
      title: new Set(),
      subtitle: new Set(),
      description: new Set(),
      whats_new: new Set(),
    }
    for (const d of localeDiffs) {
      if (d.field === 'locale_added' || d.field === 'locale_removed') {
        seenLocalesByField.title.add(d.locale)
      } else {
        seenLocalesByField[d.field].add(d.locale)
      }
    }
    summary.title = seenLocalesByField.title.size
    summary.subtitle = seenLocalesByField.subtitle.size
    summary.description = seenLocalesByField.description.size
    summary.whats_new = seenLocalesByField.whats_new.size

    return {
      version,
      previousVersion,
      isInitialRelease: previousVersion === null,
      localesCount: availableLocales.length,
      releaseNotesByLocale,
      availableLocales,
      localeDiffs,
      fileSizeDelta,
      summary,
    }
  })
}

export default function ChangesTab({ listings, versions }: ChangesTabProps) {
  const timeline = useMemo(() => buildTimeline(listings, versions), [listings, versions])

  const [filters, setFilters] = useState<Set<ChangeKind>>(new Set())
  const [expandedNotes, setExpandedNotes] = useState<Set<number>>(new Set())
  const [localeByVersion, setLocaleByVersion] = useState<Record<number, string>>({})
  const [modal, setModal] = useState<
    { entry: TimelineEntry; field: ChangeKind } | null
  >(null)

  if (versions.length === 0) {
    return (
      <div className="py-12 text-center">
        <History className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No version history available.</p>
      </div>
    )
  }

  const toggleFilter = (kind: ChangeKind) => {
    setFilters((prev) => {
      const next = new Set(prev)
      if (next.has(kind)) next.delete(kind)
      else next.add(kind)
      return next
    })
  }

  const clearFilters = () => setFilters(new Set())

  const isRowVisible = (kind: ChangeKind) =>
    filters.size === 0 || filters.has(kind)

  const oldestRelease = timeline[timeline.length - 1]?.version.release_date ?? null
  const oldestRelative = formatRelativeDate(oldestRelease)

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
      <section className="min-w-0 space-y-4">
        <header className="space-y-1">
          <h2 className="text-lg font-semibold tracking-tight">Update Timeline</h2>
          <p className="text-xs text-muted-foreground">
            {versions.length} update{versions.length === 1 ? '' : 's'}
            {oldestRelative ? ` · first released ${oldestRelative}` : ''}
          </p>
        </header>

        <div className="space-y-3">
          {timeline.map((entry) => (
            <VersionCard
              key={entry.version.id}
              entry={entry}
              isRowVisible={isRowVisible}
              expanded={expandedNotes.has(entry.version.id)}
              onToggleExpanded={() => {
                setExpandedNotes((prev) => {
                  const next = new Set(prev)
                  if (next.has(entry.version.id)) next.delete(entry.version.id)
                  else next.add(entry.version.id)
                  return next
                })
              }}
              selectedLocale={
                localeByVersion[entry.version.id] ??
                pickDefaultLocale(entry.availableLocales)
              }
              onLocaleChange={(loc) =>
                setLocaleByVersion((prev) => ({ ...prev, [entry.version.id]: loc }))
              }
              onRowClick={(field) => setModal({ entry, field })}
            />
          ))}
        </div>
      </section>

      <aside className="lg:sticky lg:top-4 lg:self-start">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0 pb-3">
            <CardTitle className="text-sm">Filters</CardTitle>
            <Button
              variant="ghost"
              size="sm"
              className="h-auto p-1 text-xs text-muted-foreground hover:text-foreground"
              onClick={clearFilters}
              disabled={filters.size === 0}
            >
              Clear all
            </Button>
          </CardHeader>
          <CardContent className="space-y-2">
            {FILTER_OPTIONS.map((opt) => (
              <label
                key={opt.kind}
                className="flex cursor-pointer items-center gap-2 text-sm"
              >
                <input
                  type="checkbox"
                  checked={filters.has(opt.kind)}
                  onChange={() => toggleFilter(opt.kind)}
                  className="h-4 w-4 cursor-pointer rounded border-input accent-primary"
                />
                <span>{opt.label}</span>
              </label>
            ))}
          </CardContent>
        </Card>
      </aside>

      <DiffModal state={modal} onClose={() => setModal(null)} />
    </div>
  )
}

interface VersionCardProps {
  entry: TimelineEntry
  isRowVisible: (kind: ChangeKind) => boolean
  expanded: boolean
  onToggleExpanded: () => void
  selectedLocale: string
  onLocaleChange: (locale: string) => void
  onRowClick: (field: ChangeKind) => void
}

function VersionCard({
  entry,
  isRowVisible,
  expanded,
  onToggleExpanded,
  selectedLocale,
  onLocaleChange,
  onRowClick,
}: VersionCardProps) {
  const relative = formatRelativeDate(entry.version.release_date ?? null)
  const notes = entry.releaseNotesByLocale[selectedLocale] ?? null
  const hasLongNotes = (notes?.length ?? 0) > 240

  const visibleRows: { kind: ChangeKind; node: React.ReactNode }[] = []

  if (!entry.isInitialRelease) {
    if (entry.fileSizeDelta && isRowVisible('file_size')) {
      const { direction, bytes } = entry.fileSizeDelta
      visibleRows.push({
        kind: 'file_size',
        node: (
          <ChangeRow
            icon={
              direction === 'decreased' ? (
                <ArrowDown className="h-3.5 w-3.5 text-green-600" />
              ) : (
                <ArrowUp className="h-3.5 w-3.5 text-amber-600" />
              )
            }
            onClick={() => onRowClick('file_size')}
          >
            File size {direction} by <strong>{formatBytes(bytes)}</strong>
          </ChangeRow>
        ),
      })
    }
    for (const f of TEXT_FIELDS) {
      const count = entry.summary[f]
      if (count > 0 && isRowVisible(f)) {
        visibleRows.push({
          kind: f,
          node: (
            <ChangeRow
              icon={<RefreshCw className="h-3.5 w-3.5 text-muted-foreground" />}
              onClick={() => onRowClick(f)}
            >
              <strong>{FIELD_LABELS[f]}</strong> changed in {count}{' '}
              language{count !== 1 ? 's' : ''}
            </ChangeRow>
          ),
        })
      }
    }
  }

  return (
    <Card>
      <CardHeader className="flex flex-row flex-wrap items-center gap-3 space-y-0 pb-3">
        <CardTitle className="text-base">v{entry.version.version}</CardTitle>
        {entry.availableLocales.length > 0 && selectedLocale && (
          <Select value={selectedLocale} onValueChange={(v) => v && onLocaleChange(v)}>
            <SelectTrigger className="ml-auto h-8 w-auto min-w-[160px] gap-1.5 text-xs">
              <Globe className="h-3.5 w-3.5 text-muted-foreground" />
              <SelectValue>
                <span>
                  {localeName(selectedLocale)}
                  <span className="ml-1 text-muted-foreground">· {entry.localesCount}</span>
                </span>
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {entry.availableLocales.map((loc) => (
                <SelectItem key={loc} value={loc}>
                  <span className="flex items-baseline gap-1.5">
                    <span>{localeName(loc)}</span>
                    <span className="text-[10px] text-muted-foreground">{loc}</span>
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </CardHeader>

      <CardContent className="space-y-3 pb-4">
        {(notes !== null || entry.availableLocales.length > 0) && (
          <div>
            {notes ? (
              <p
                dir="auto"
                className={`whitespace-pre-line text-sm ${
                  !expanded && hasLongNotes ? 'line-clamp-3' : ''
                }`}
              >
                {notes}
              </p>
            ) : (
              <p className="text-sm text-muted-foreground">
                No release notes for this locale.
              </p>
            )}
            {notes && hasLongNotes && (
              <button
                type="button"
                onClick={onToggleExpanded}
                className="mt-1.5 cursor-pointer text-xs font-medium text-primary hover:underline"
              >
                {expanded ? 'Show less' : 'Show more'}
              </button>
            )}
          </div>
        )}

        {!entry.isInitialRelease && visibleRows.length > 0 && (
          <div className="border-t pt-2">
            {visibleRows.map((r, i) => (
              <div key={`${r.kind}-${i}`}>{r.node}</div>
            ))}
          </div>
        )}

        {!entry.isInitialRelease && visibleRows.length === 0 && (
          <div className="border-t pt-3 text-xs text-muted-foreground">
            No tracked metadata changes{filtersEmptyHint(isRowVisible)}.
          </div>
        )}
      </CardContent>

      {(relative || entry.isInitialRelease) && (
        <CardFooter className="flex flex-wrap items-center gap-2 border-t-0 bg-transparent pt-0 text-xs text-muted-foreground">
          {relative && (
            <span className="inline-flex h-5 items-center leading-none">{relative}</span>
          )}
          {entry.isInitialRelease && (
            <Badge variant="outline" className="gap-1">
              <Sparkles className="h-3 w-3" />
              Initial release
            </Badge>
          )}
        </CardFooter>
      )}
    </Card>
  )
}

function filtersEmptyHint(isRowVisible: (k: ChangeKind) => boolean): string {
  const anyHidden = (['title', 'subtitle', 'description', 'whats_new', 'file_size'] as ChangeKind[]).some(
    (k) => !isRowVisible(k),
  )
  return anyHidden ? ' match the active filters' : ''
}

interface ChangeRowProps {
  icon: React.ReactNode
  children: React.ReactNode
  onClick: () => void
}

function ChangeRow({ icon, children, onClick }: ChangeRowProps) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="group flex w-full cursor-pointer items-center gap-2 rounded-md px-1 py-1.5 text-left text-sm hover:bg-accent/50"
    >
      {icon}
      <span className="flex-1 text-muted-foreground">{children}</span>
      <ChevronRight className="h-3.5 w-3.5 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
    </button>
  )
}

function localeName(locale: string): string {
  const [lang, region] = locale.split(/[-_]/)
  try {
    const langDn = new Intl.DisplayNames(['en'], { type: 'language' })
    const langName = langDn.of(lang) ?? lang.toUpperCase()
    if (region) {
      const regionDn = new Intl.DisplayNames(['en'], { type: 'region' })
      const regionName = regionDn.of(region.toUpperCase()) ?? region.toUpperCase()
      return `${langName} (${regionName})`
    }
    return langName
  } catch {
    return locale.toUpperCase()
  }
}

interface DiffModalProps {
  state: { entry: TimelineEntry; field: ChangeKind } | null
  onClose: () => void
}

function DiffModal({ state, onClose }: DiffModalProps) {
  const open = state !== null

  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        {state && <DiffModalBody state={state} />}
      </DialogContent>
    </Dialog>
  )
}

function DiffModalBody({
  state,
}: {
  state: { entry: TimelineEntry; field: ChangeKind }
}) {
  const { entry, field } = state
  const prev = entry.previousVersion
  const titleRange = prev
    ? `v${prev.version} → v${entry.version.version}`
    : `v${entry.version.version}`

  if (field === 'file_size') {
    const prevBytes = prev?.file_size_bytes ?? null
    const currBytes = entry.version.file_size_bytes ?? null
    return (
      <>
        <DialogHeader>
          <DialogTitle>
            {FIELD_LABELS.file_size} — {titleRange}
          </DialogTitle>
        </DialogHeader>
        <div className="mt-2 grid gap-2 sm:grid-cols-2">
          <div className="rounded-md bg-red-500/5 p-3">
            <span className="text-[10px] font-semibold uppercase text-red-500">
              Before{prev ? ` (v${prev.version})` : ''}
            </span>
            <p className="mt-1 text-sm">{prevBytes != null ? formatBytes(prevBytes) : '—'}</p>
          </div>
          <div className="rounded-md bg-green-500/5 p-3">
            <span className="text-[10px] font-semibold uppercase text-green-500">
              After (v{entry.version.version})
            </span>
            <p className="mt-1 text-sm">{currBytes != null ? formatBytes(currBytes) : '—'}</p>
          </div>
        </div>
        {entry.fileSizeDelta && (
          <p className="mt-2 text-xs text-muted-foreground">
            {entry.fileSizeDelta.direction === 'decreased' ? 'Decreased' : 'Increased'} by{' '}
            {formatBytes(entry.fileSizeDelta.bytes)}.
          </p>
        )}
      </>
    )
  }

  const fieldDiffs = entry.localeDiffs.filter((d) => {
    if (field === 'title') {
      return d.field === 'title' || d.field === 'locale_added' || d.field === 'locale_removed'
    }
    return d.field === field
  })

  return (
    <>
      <DialogHeader>
        <DialogTitle>
          {FIELD_LABELS[field]} — {titleRange}
        </DialogTitle>
      </DialogHeader>
      <div className="mt-2 space-y-3">
        {fieldDiffs.length === 0 ? (
          <p className="text-sm text-muted-foreground">No differences for this field.</p>
        ) : (
          fieldDiffs.map((d, i) => (
            <ChangeFieldRow
              key={`${d.locale}-${d.field}-${i}`}
              locale={d.locale}
              field={d.field}
              oldValue={d.oldValue}
              newValue={d.newValue}
              beforeVersion={prev?.version}
              afterVersion={entry.version.version}
            />
          ))
        )}
      </div>
    </>
  )
}
