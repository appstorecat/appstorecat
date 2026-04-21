import { useMemo, useState } from 'react'
import { ArrowUp, ArrowDown, Minus, Trophy, ChevronLeft, ChevronRight, Calendar } from 'lucide-react'
import { useCountries } from '@/components/CountrySelect'
import QueryError from '@/components/QueryError'
import { useListAppRankings } from '@/api/endpoints/apps/apps'
import type { AppRankingResource } from '@/api/models/appRankingResource'
import { AppRankingResourceStatus } from '@/api/models/appRankingResourceStatus'

type RankStatus = AppRankingResourceStatus
type Collection = 'top_free' | 'top_paid' | 'top_grossing'

type CurrentRanking = AppRankingResource & {
  country_code: string
  collection: Collection
  rank: number
  status: RankStatus
  snapshot_date: string
}

interface RankingsTabProps {
  platform: string
  externalId: string
  selectedCountry: string
}

const COLLECTION_SHORT: Record<Collection, string> = {
  top_free: 'Free',
  top_paid: 'Paid',
  top_grossing: 'Top Grossing',
}

type RankTypeFilter = 'any' | Collection

function todayIso(): string {
  return new Date().toISOString().slice(0, 10)
}

function shiftDate(iso: string, deltaDays: number): string {
  const d = new Date(iso + 'T00:00:00Z')
  d.setUTCDate(d.getUTCDate() + deltaDays)
  return d.toISOString().slice(0, 10)
}

export default function RankingsTab({ platform, externalId }: RankingsTabProps) {
  const { data: countries } = useCountries()

  const [selectedDate, setSelectedDate] = useState<string>(todayIso())
  const [rankType, setRankType] = useState<RankTypeFilter>('any')

  // Platform comes from the parent as a plain string; backend enum is lowercase.
  const platformParam = platform as 'ios' | 'android'

  const { data, isLoading, isError, refetch } = useListAppRankings(
    platformParam,
    externalId,
    {
      date: selectedDate,
      collection: rankType === 'any' ? 'all' : rankType,
    },
  )
  const current = useMemo<CurrentRanking[]>(() => (data ?? []) as CurrentRanking[], [data])

  // Each column represents a (collection, category) tuple.
  type Column = {
    key: string // collection|categoryKey
    collection: Collection
    categoryId: string // 'overall' or numeric id
    categoryName: string
  }

  const visibleColumns = useMemo<Column[]>(() => {
    const map = new Map<string, Column>()
    for (const row of current) {
      const categoryId = row.category ? String(row.category.id) : 'overall'
      const categoryName = row.category?.name ?? 'Overall'
      const key = `${row.collection}|${categoryId}`
      if (!map.has(key)) {
        map.set(key, { key, collection: row.collection, categoryId, categoryName })
      }
    }
    const collOrder: Collection[] = ['top_free', 'top_paid', 'top_grossing']
    return Array.from(map.values()).sort((a, b) => {
      // Overall first within same collection, then by category name.
      const ci = collOrder.indexOf(a.collection) - collOrder.indexOf(b.collection)
      if (ci !== 0) return ci
      if (a.categoryId === 'overall') return -1
      if (b.categoryId === 'overall') return 1
      return a.categoryName.localeCompare(b.categoryName)
    })
  }, [current])

  const pivoted = useMemo(() => {
    const byCountry = new Map<string, Record<string, CurrentRanking>>()
    for (const row of current) {
      const categoryId = row.category ? String(row.category.id) : 'overall'
      const key = `${row.collection}|${categoryId}`
      if (!byCountry.has(row.country_code)) byCountry.set(row.country_code, {})
      byCountry.get(row.country_code)![key] = row
    }
    const entries = Array.from(byCountry.entries()).map(([code, cols]) => {
      const ranks = Object.values(cols)
        .map((r) => r.rank)
        .filter((r): r is number => typeof r === 'number')
      const best = ranks.length > 0 ? Math.min(...ranks) : Number.MAX_SAFE_INTEGER
      return { code, cols, best }
    })
    entries.sort((a, b) => a.best - b.best)
    return entries
  }, [current])

  const countryCount = pivoted.length
  const isToday = selectedDate === todayIso()

  if (isError) {
    return <QueryError message="Failed to load rankings." onRetry={() => refetch()} />
  }

  return (
    <div className="flex flex-col gap-5">
      {/* Filter bar */}
      <div className="flex flex-wrap items-end gap-6">
        <FilterGroup label="Rank Type">
          <div className="inline-flex h-9 items-center rounded-md border bg-background p-0.5">
            {([
              ['any', 'Any'],
              ['top_free', 'Top Free'],
              ['top_paid', 'Top Paid'],
              ['top_grossing', 'Top Grossing'],
            ] as [RankTypeFilter, string][]).map(([value, label]) => (
              <button
                key={value}
                type="button"
                onClick={() => setRankType(value)}
                className={`flex h-full items-center rounded px-3 text-xs font-medium transition-colors ${
                  rankType === value
                    ? 'bg-accent text-accent-foreground shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </FilterGroup>

        <FilterGroup label="Date">
          <div className="inline-flex h-9 items-center rounded-md border bg-background">
            <button
              type="button"
              onClick={() => setSelectedDate((d) => shiftDate(d, -1))}
              className="flex h-full w-8 cursor-pointer items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
              aria-label="Previous date"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            <label className="flex cursor-pointer items-center gap-1.5 px-1 text-xs font-medium">
              <Calendar className="h-3.5 w-3.5 text-muted-foreground" />
              <input
                type="date"
                value={selectedDate}
                onChange={(e) => e.target.value && setSelectedDate(e.target.value)}
                className="cursor-pointer bg-transparent text-foreground outline-none [color-scheme:dark]"
              />
            </label>
            <button
              type="button"
              onClick={() => setSelectedDate((d) => shiftDate(d, 1))}
              disabled={isToday}
              className="flex h-full w-8 cursor-pointer items-center justify-center text-muted-foreground transition-colors hover:text-foreground disabled:cursor-not-allowed disabled:text-muted-foreground/40"
              aria-label="Next date"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>
        </FilterGroup>
      </div>

      {/* Summary line */}
      <p className="text-sm text-muted-foreground">
        Ranked in <span className="font-semibold text-foreground">{countryCount}</span>{' '}
        {countryCount === 1 ? 'country' : 'countries'} around the world
      </p>

      {/* Content */}
      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <div className="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : current.length === 0 ? (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Trophy className="mx-auto mb-4 h-10 w-10 text-muted-foreground" />
          <p className="text-sm text-muted-foreground">
            No rankings for this date.
          </p>
        </div>
      ) : visibleColumns.length === 0 ? (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <p className="text-sm text-muted-foreground">
            No rankings match the selected filters.
          </p>
        </div>
      ) : (
        <div className="overflow-hidden rounded-lg border">
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b bg-muted/30">
                  <th className="min-w-[220px] px-4 py-3 text-left align-top">
                    <div className="text-sm font-semibold">Country</div>
                    <div className="text-xs font-normal text-muted-foreground">
                      Ranked in {countryCount} {countryCount === 1 ? 'Country' : 'Countries'}
                    </div>
                  </th>
                  {visibleColumns.map((col) => (
                    <th
                      key={col.key}
                      className="min-w-[180px] border-l px-4 py-3 text-left align-top"
                    >
                      <div className="text-sm font-semibold">{col.categoryName}</div>
                      <div className="text-xs font-normal text-muted-foreground">
                        {COLLECTION_SHORT[col.collection]}
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y">
                {pivoted.map((entry, i) => {
                  const country = countries?.find((c) => c.code === entry.code)
                  return (
                    <tr key={entry.code} className="hover:bg-muted/20">
                      <td className="px-4 py-2.5">
                        <div className="flex items-center gap-2 text-sm">
                          {country?.emoji && (
                            <span className="text-base leading-none">{country.emoji}</span>
                          )}
                          <span className="text-muted-foreground">{i + 1}.</span>
                          <span className="font-medium">
                            {country?.name ?? entry.code.toUpperCase()}
                          </span>
                        </div>
                      </td>
                      {visibleColumns.map((col) => {
                        const row = entry.cols[col.key]
                        return (
                          <td key={col.key} className="border-l px-4 py-2.5">
                            <RankCell row={row} />
                          </td>
                        )
                      })}
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  )
}

function FilterGroup({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-xs font-medium text-muted-foreground">{label}</span>
      {children}
    </div>
  )
}

function RankCell({ row }: { row: CurrentRanking | undefined }) {
  if (!row) {
    return (
      <div className="flex items-center justify-end">
        <span className="text-sm text-muted-foreground/60">N/A</span>
      </div>
    )
  }
  return (
    <div className="flex items-center justify-between gap-3">
      <RankDelta status={row.status} change={row.rank_change} />
      <span className="text-sm font-semibold tabular-nums">{row.rank}</span>
    </div>
  )
}

function RankDelta({ status, change }: { status: RankStatus; change: number | null | undefined }) {
  if (status === 'new') {
    return (
      <span className="rounded border border-emerald-500/40 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-500">
        NEW
      </span>
    )
  }
  if (status === 'same' || change === 0 || change === null || change === undefined) {
    return <Minus className="h-3 w-3 text-muted-foreground/60" />
  }
  if (status === 'up') {
    return (
      <span className="flex items-center gap-0.5 text-xs font-medium text-emerald-500">
        <ArrowUp className="h-3 w-3" />
        {change}
      </span>
    )
  }
  return (
    <span className="flex items-center gap-0.5 text-xs font-medium text-red-500">
      <ArrowDown className="h-3 w-3" />
      {Math.abs(change)}
    </span>
  )
}
