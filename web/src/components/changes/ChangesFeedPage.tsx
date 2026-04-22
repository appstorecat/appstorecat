import { useMemo, useState } from 'react'
import { keepPreviousData } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import FilterBar from '@/components/FilterBar'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'

import { useAppChanges, useCompetitorChanges } from '@/api/endpoints/change-monitor/change-monitor'
import {
  AppChangesField,
  AppChangesPlatform,
  CompetitorChangesField,
  CompetitorChangesPlatform,
  type AppChangesParams,
  type CompetitorChangesParams,
  type PaginatedChangeResponse,
} from '@/api/models'

import { useChangesFilters, type ChangesField, type ChangesPlatform } from './useChangesFilters'
import ChangeGroupCard from './ChangeGroupCard'
import { bucketByDateSection, groupChanges } from './groupChanges'

export type ChangesMode = 'tracked' | 'competitors'

interface ChangesFeedPageProps {
  mode: ChangesMode
}

const FIELD_OPTIONS: { value: ChangesField; label: string }[] = [
  { value: 'all', label: 'All fields' },
  { value: 'title', label: 'Title' },
  { value: 'subtitle', label: 'Subtitle' },
  { value: 'description', label: 'Description' },
  { value: 'whats_new', label: "What's New" },
  { value: 'screenshots', label: 'Screenshots' },
  { value: 'locale_added', label: 'Locale Added' },
  { value: 'locale_removed', label: 'Locale Removed' },
]

const PAGE_SIZE = 50

export default function ChangesFeedPage({ mode }: ChangesFeedPageProps) {
  const filters = useChangesFilters()
  // Remount the inner feed whenever any filter changes so page state resets
  // without running a setState-in-effect pattern.
  const filterKey = `${filters.platform}|${filters.field}|${filters.search}`
  return <ChangesFeed key={filterKey} mode={mode} filters={filters} />
}

function ChangesFeed({
  mode,
  filters,
}: {
  mode: ChangesMode
  filters: ReturnType<typeof useChangesFilters>
}) {
  const [page, setPage] = useState(1)

  const title = mode === 'tracked' ? 'App Changes' : 'Competitor Changes'
  const subtitle =
    mode === 'tracked'
      ? 'Store listing changes across your tracked apps'
      : 'Store listing changes across your competitor apps'

  const rows = useChangesFeed(mode, filters, page)
  const grouped = useMemo(() => groupChanges(rows.data), [rows.data])
  const sections = useMemo(() => bucketByDateSection(grouped), [grouped])

  return (
    <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
      <header>
        <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
        <p className="text-sm text-muted-foreground">{subtitle}</p>
      </header>

      <FilterBar>
        <FilterBar.Search
          value={filters.search}
          onChange={filters.setSearch}
          placeholder="Search by app name…"
        />
        <FilterBar.Controls>
          <Select value={filters.field} onValueChange={(v) => filters.setField(v as ChangesField)}>
            <SelectTrigger className="w-full sm:w-[180px]">
              <SelectValue>
                {FIELD_OPTIONS.find((o) => o.value === filters.field)?.label ?? 'All fields'}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              {FIELD_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>
                  {o.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

          <PlatformTabs value={filters.platform} onChange={filters.setPlatform} />
        </FilterBar.Controls>
      </FilterBar>


      {rows.isPending ? (
        <FeedSkeleton />
      ) : rows.data.length === 0 ? (
        <EmptyState
          mode={mode}
          hasScopeApps={rows.hasScopeApps}
          hasAnyFilter={filters.hasAny}
          onClearFilters={filters.clearAll}
        />
      ) : (
        <div className="space-y-6">
          {sections.map((section) => (
            <section key={section.section} className="space-y-3">
              <h2 className="sticky top-14 z-10 -mx-4 bg-background/85 px-4 py-1 text-xs font-semibold uppercase tracking-widest text-muted-foreground backdrop-blur md:-mx-6 md:px-6">
                {section.label}
              </h2>
              <div className="space-y-3">
                {section.groups.map((group) => (
                  <ChangeGroupCard key={group.key} group={group} />
                ))}
              </div>
            </section>
          ))}

          {rows.hasNext && (
            <div className="flex justify-center pt-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => p + 1)}
                disabled={rows.isFetching}
              >
                {rows.isFetching ? 'Loading…' : 'Load more'}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}

// --- Feed data hook ---------------------------------------------------------

function useChangesFeed(
  mode: ChangesMode,
  filters: ReturnType<typeof useChangesFilters>,
  page: number,
) {
  const shared = {
    per_page: PAGE_SIZE,
    page,
    ...(filters.search.trim().length > 0 ? { search: filters.search.trim() } : {}),
  } as const

  const trackedParams: AppChangesParams = {
    ...shared,
    ...(filters.platform !== 'all' ? { platform: AppChangesPlatform[filters.platform] } : {}),
    ...(filters.field !== 'all'
      ? { field: AppChangesField[filters.field as keyof typeof AppChangesField] }
      : {}),
  }

  const competitorParams: CompetitorChangesParams = {
    ...shared,
    ...(filters.platform !== 'all' ? { platform: CompetitorChangesPlatform[filters.platform] } : {}),
    ...(filters.field !== 'all'
      ? { field: CompetitorChangesField[filters.field as keyof typeof CompetitorChangesField] }
      : {}),
  }

  const trackedQuery = useAppChanges(trackedParams, {
    query: { placeholderData: keepPreviousData, enabled: mode === 'tracked' },
  })
  const competitorQuery = useCompetitorChanges(competitorParams, {
    query: { placeholderData: keepPreviousData, enabled: mode === 'competitors' },
  })

  const active = mode === 'tracked' ? trackedQuery : competitorQuery
  const payload = active.data as PaginatedChangeResponse | undefined

  return {
    data: payload?.data ?? [],
    total: payload?.meta?.total ?? 0,
    isPending: active.isPending,
    isFetching: active.isFetching,
    hasScopeApps: payload?.meta_ext?.has_scope_apps ?? false,
    hasNext:
      !!payload?.meta &&
      typeof payload.meta.current_page === 'number' &&
      typeof payload.meta.last_page === 'number' &&
      payload.meta.current_page < payload.meta.last_page,
  }
}

// --- Filter widgets ---------------------------------------------------------

function PlatformTabs({
  value,
  onChange,
}: {
  value: ChangesPlatform
  onChange: (v: ChangesPlatform) => void
}) {
  return (
    <div className="flex w-full items-center rounded-lg border bg-background p-0.5 sm:inline-flex sm:w-auto">
      <PlatformTab active={value === 'all'} onClick={() => onChange('all')}>
        All
      </PlatformTab>
      <PlatformTab active={value === 'ios'} onClick={() => onChange('ios')}>
        <AppStoreSvg className="h-4 w-4" />
        <span className="hidden sm:inline">App Store</span>
      </PlatformTab>
      <PlatformTab active={value === 'android'} onClick={() => onChange('android')}>
        <GooglePlaySvg className="h-4 w-4" />
        <span className="hidden sm:inline">Google Play</span>
      </PlatformTab>
    </div>
  )
}

function PlatformTab({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: React.ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex flex-1 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors sm:flex-initial ${
        active
          ? 'bg-accent text-accent-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  )
}

function FeedSkeleton() {
  return (
    <div className="space-y-3">
      {Array.from({ length: 5 }).map((_, i) => (
        <Skeleton key={i} className="h-20 w-full" />
      ))}
    </div>
  )
}

function EmptyState({
  mode,
  hasScopeApps,
  hasAnyFilter,
  onClearFilters,
}: {
  mode: ChangesMode
  hasScopeApps: boolean
  hasAnyFilter: boolean
  onClearFilters: () => void
}) {
  if (hasAnyFilter) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed py-16 text-center">
        <p className="text-sm font-medium">No changes match these filters</p>
        <Button variant="outline" size="sm" onClick={onClearFilters}>
          Clear filters
        </Button>
      </div>
    )
  }

  if (!hasScopeApps) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed py-16 text-center">
        <p className="text-sm font-medium">
          {mode === 'tracked'
            ? 'Start tracking apps to see their store listing changes'
            : 'Add competitors to your tracked apps to see their changes'}
        </p>
        <Link
          to={mode === 'tracked' ? '/discovery/apps' : '/apps'}
          className="text-xs font-medium text-primary hover:underline"
        >
          {mode === 'tracked' ? 'Discover apps →' : 'Open tracked apps →'}
        </Link>
      </div>
    )
  }

  return (
    <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-16 text-center">
      <p className="text-sm font-medium">No changes detected yet</p>
      <p className="text-xs text-muted-foreground">
        We're watching — anything new will show up here.
      </p>
    </div>
  )
}
