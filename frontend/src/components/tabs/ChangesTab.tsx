import { useMemo, useState } from 'react'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { History, ArrowRight } from 'lucide-react'
import ChangeCard from '@/components/ChangeCard'

interface ListingData {
  id: number
  version_id: number | null
  lang: string
  title: string
  subtitle: string | null
  description: string
  whats_new: string | null
  icon_url: string | null
  screenshots: { url: string; device_type: string; order: number }[] | null
}

interface AppVersionData {
  id: number
  version: string
  release_date: string | null
  whats_new: string | null
  file_size_bytes: number | null
  created_at: string
}

interface ChangesTabProps {
  listings: ListingData[]
  versions: AppVersionData[]
}

interface FieldDiff {
  lang: string
  field: string
  oldValue: string | null
  newValue: string | null
}

function languageDisplay(lang: string) {
  let name = lang.toUpperCase()
  try {
    const display = new Intl.DisplayNames(['en'], { type: 'region' })
    name = display.of(lang.toUpperCase()) ?? name
  } catch { /* fallback */ }

  const flagUrl = `https://flagcdn.com/w40/${lang}.png`
  return { name, flagUrl }
}

const COMPARE_FIELDS = ['title', 'subtitle', 'description', 'whats_new'] as const

function computeDiffs(
  oldListings: ListingData[],
  newListings: ListingData[],
): FieldDiff[] {
  const diffs: FieldDiff[] = []

  const oldByCountry = new Map(oldListings.map((l) => [l.language, l]))
  const newByCountry = new Map(newListings.map((l) => [l.language, l]))

  const allLanguages = new Set([...oldByCountry.keys(), ...newByCountry.keys()])

  for (const cc of allLanguages) {
    const oldL = oldByCountry.get(cc)
    const newL = newByCountry.get(cc)

    if (!oldL && newL) {
      diffs.push({ lang: cc, field: 'language_added', oldValue: null, newValue: newL.title })
      continue
    }

    if (oldL && !newL) {
      diffs.push({ lang: cc, field: 'language_removed', oldValue: oldL.title, newValue: null })
      continue
    }

    if (oldL && newL) {
      for (const field of COMPARE_FIELDS) {
        const ov = oldL[field] ?? null
        const nv = newL[field] ?? null
        if (ov !== nv) {
          diffs.push({ lang: cc, field, oldValue: ov, newValue: nv })
        }
      }

    }
  }

  return diffs
}


export default function ChangesTab({ listings, versions }: ChangesTabProps) {
  const sortedVersions = useMemo(
    () => [...versions].sort((a, b) => b.id - a.id),
    [versions],
  )

  const [mainVersionId, setMainVersionId] = useState<string>(
    sortedVersions[0]?.id?.toString() ?? '',
  )
  const [compareVersionId, setCompareVersionId] = useState<string>(
    sortedVersions[1]?.id?.toString() ?? '',
  )

  const mainVersion = sortedVersions.find((v) => v.id.toString() === mainVersionId)
  const compareVersion = sortedVersions.find((v) => v.id.toString() === compareVersionId)

  const diffs = useMemo(() => {
    if (!mainVersionId || !compareVersionId || mainVersionId === compareVersionId) return []

    const mainId = parseInt(mainVersionId)
    const compareId = parseInt(compareVersionId)

    const mainListings = listings.filter((l) => l.version_id === mainId)
    const compareListings = listings.filter((l) => l.version_id === compareId)

    return computeDiffs(compareListings, mainListings)
  }, [listings, mainVersionId, compareVersionId])

  // Group diffs by language
  const groupedByLanguage = useMemo(() => {
    const groups: Record<string, FieldDiff[]> = {}
    for (const d of diffs) {
      if (!groups[d.lang]) groups[d.lang] = []
      groups[d.lang].push(d)
    }
    return groups
  }, [diffs])

  const languageEntries = Object.entries(groupedByLanguage)

  if (sortedVersions.length < 2) {
    return (
      <div className="py-12 text-center">
        <History className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">Not enough versions to compare.</p>
        <p className="mt-1 text-sm text-muted-foreground/70">
          Changes will appear here after the app is rebuilt with a new version.
        </p>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Version selectors */}
      <div className="flex flex-wrap items-center gap-2">
        <Select value={compareVersionId} onValueChange={(v) => v && setCompareVersionId(v)}>
          <SelectTrigger className="w-[110px]">
            <SelectValue>
              {compareVersion ? `v${compareVersion.version}` : 'Select'}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {sortedVersions
              .filter((v) => v.id.toString() !== mainVersionId)
              .map((v) => (
                <SelectItem key={v.id} value={v.id.toString()}>
                  v{v.version}
                </SelectItem>
              ))}
          </SelectContent>
        </Select>

        <ArrowRight className="h-4 w-4 text-muted-foreground" />

        <Select value={mainVersionId} onValueChange={(v) => v && setMainVersionId(v)}>
          <SelectTrigger className="w-[110px]">
            <SelectValue>
              {mainVersion ? `v${mainVersion.version}` : 'Select'}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {sortedVersions
              .filter((v) => v.id.toString() !== compareVersionId)
              .map((v) => (
                <SelectItem key={v.id} value={v.id.toString()}>
                  v{v.version}
                </SelectItem>
              ))}
          </SelectContent>
        </Select>

        {diffs.length > 0 && (
          <Badge variant="secondary" className="text-xs">
            {diffs.length} change{diffs.length !== 1 ? 's' : ''}
          </Badge>
        )}
      </div>

      {/* Diffs */}
      {diffs.length === 0 ? (
        <div className="py-8 text-center">
          <p className="text-sm text-muted-foreground">No differences between these versions.</p>
        </div>
      ) : (
        <div className="space-y-6">
          {languageEntries.map(([cc, countryDiffs]) => {
            const { name, flagUrl } = languageDisplay(cc)
            return (
            <div key={cc} className="space-y-2">
              <h3 className="flex items-center gap-2 text-sm font-medium">
                {flagUrl && <img src={flagUrl} alt="" className="h-3.5 w-5 rounded-sm object-cover" />}
                {name}
                <span className="text-xs text-muted-foreground">{cc}</span>
                <Badge variant="outline" className="text-[10px]">
                  {countryDiffs.length} change{countryDiffs.length !== 1 ? 's' : ''}
                </Badge>
              </h3>
              <div className="space-y-2">
                {countryDiffs.map((diff, i) => (
                  <ChangeCard
                    key={`${diff.lang}-${diff.field}-${i}`}
                    lang={diff.lang}
                    fieldChanged={diff.field}
                    oldValue={diff.oldValue}
                    newValue={diff.newValue}
                    beforeVersion={compareVersion?.version}
                    afterVersion={mainVersion?.version}
                    detectedAt={new Date().toISOString()}
                    showAppInfo={false}
                  />
                ))}
              </div>
            </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
