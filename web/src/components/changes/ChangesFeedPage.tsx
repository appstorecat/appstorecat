import { useEffect, useMemo, useRef } from 'react'
import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query'
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

import {
  appChanges,
  competitorChanges,
  getAppChangesQueryKey,
  getCompetitorChangesQueryKey,
} from '@/api/endpoints/change-monitor/change-monitor'
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
import { groupChanges } from './groupChanges'
import { useDebounce } from '@/hooks/use-debounce'

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
  const debouncedSearch = useDebounce(filters.search)
  const sentinelRef = useRef<HTMLDivElement>(null)

  const title = mode === 'tracked' ? 'App Changes' : 'Competitor Changes'
  const subtitle =
    mode === 'tracked'
      ? 'Store listing changes across your tracked apps'
      : 'Store listing changes across your competitor apps'

  const baseParams = useMemo(() => {
    const trimmed = debouncedSearch.trim()
    return {
      per_page: PAGE_SIZE,
      ...(trimmed.length > 0 ? { search: trimmed } : {}),
      ...(filters.platform !== 'all'
        ? mode === 'tracked'
          ? { platform: AppChangesPlatform[filters.platform] }
          : { platform: CompetitorChangesPlatform[filters.platform] }
        : {}),
      ...(filters.field !== 'all'
        ? mode === 'tracked'
          ? { field: AppChangesField[filters.field as keyof typeof AppChangesField] }
          : { field: CompetitorChangesField[filters.field as keyof typeof CompetitorChangesField] }
        : {}),
    }
  }, [debouncedSearch, filters.platform, filters.field, mode])

  const queryKey =
    mode === 'tracked'
      ? getAppChangesQueryKey(baseParams as AppChangesParams)
      : getCompetitorChangesQueryKey(baseParams as CompetitorChangesParams)

  const {
    data,
    isPending,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery<PaginatedChangeResponse, unknown, InfiniteData<PaginatedChangeResponse, number>, readonly unknown[], number>({
    queryKey: queryKey as readonly unknown[],
    queryFn: ({ pageParam, signal }) =>
      mode === 'tracked'
        ? (appChanges({ ...(baseParams as AppChangesParams), page: pageParam }, undefined, signal) as Promise<PaginatedChangeResponse>)
        : (competitorChanges({ ...(baseParams as CompetitorChangesParams), page: pageParam }, undefined, signal) as Promise<PaginatedChangeResponse>),
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      const meta = lastPage.meta as { current_page?: number; last_page?: number } | undefined
      const current = meta?.current_page ?? 0
      const last = meta?.last_page ?? 0
      return current < last ? current + 1 : undefined
    },
  })

  const rows = useMemo(() => data?.pages.flatMap((p) => p.data ?? []) ?? [], [data])
  const grouped = useMemo(() => groupChanges(rows), [rows])
  const hasScopeApps = useMemo(() => {
    const last = data?.pages[data.pages.length - 1]
    return last?.meta_ext?.has_scope_apps ?? false
  }, [data])

  // Infinite scroll observer
  useEffect(() => {
    const el = sentinelRef.current
    if (!el) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && hasNextPage && !isFetchingNextPage) {
          fetchNextPage()
        }
      },
      { rootMargin: '400px' },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage])

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

      {isPending ? (
        <FeedSkeleton />
      ) : rows.length === 0 ? (
        <EmptyState
          mode={mode}
          hasScopeApps={hasScopeApps}
          hasAnyFilter={filters.hasAny}
          onClearFilters={filters.clearAll}
        />
      ) : (
        <div className="space-y-4">
          {grouped.map((group) => (
            <ChangeGroupCard key={group.key} group={group} />
          ))}

          <div ref={sentinelRef} className="h-1" />

          {isFetchingNextPage && (
            <div className="flex items-center justify-center py-6">
              <div className="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
            </div>
          )}
        </div>
      )}
    </div>
  )
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
