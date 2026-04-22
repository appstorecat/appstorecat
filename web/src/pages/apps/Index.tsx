import { type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { keepPreviousData } from '@tanstack/react-query'
import { Smartphone } from 'lucide-react'
import { useListApps } from '@/api/endpoints/apps/apps'
import { ListAppsPlatform, type ListAppsParams } from '@/api/models'
import AppCard from '@/components/AppCard'
import QueryError from '@/components/QueryError'
import { Skeleton } from '@/components/ui/skeleton'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import FilterBar from '@/components/FilterBar'
import { useDebounce } from '@/hooks/use-debounce'

type PlatformFilter = 'all' | 'ios' | 'android'

function parsePlatform(value: string | null): PlatformFilter {
  return value === 'ios' || value === 'android' ? value : 'all'
}

export default function AppsIndex() {
  const [searchParams, setSearchParams] = useSearchParams()

  const platform = parsePlatform(searchParams.get('platform'))
  const searchTerm = searchParams.get('search') ?? ''
  const debouncedSearch = useDebounce(searchTerm)

  const setSearchTerm = (value: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) next.set('search', value)
      else next.delete('search')
      return next
    }, { replace: true })
  }

  const setPlatform = (value: PlatformFilter) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value === 'all') next.delete('platform')
      else next.set('platform', value)
      return next
    }, { replace: true })
  }

  const queryParams: ListAppsParams = {}
  if (platform !== 'all') queryParams.platform = ListAppsPlatform[platform]
  if (debouncedSearch.trim().length > 0) queryParams.search = debouncedSearch.trim()

  const { data: apps, isError, refetch, isFetching, isPending } = useListApps(queryParams, {
    query: { placeholderData: keepPreviousData },
  })

  if (isError) {
    return <QueryError message="Failed to load apps." onRetry={() => refetch()} />
  }

  const count = apps?.length ?? 0
  const hasFilters = platform !== 'all' || debouncedSearch.trim().length > 0

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Apps</h1>
        <p className="text-sm text-muted-foreground">
          {hasFilters
            ? `${count} result${count === 1 ? '' : 's'}${isFetching ? ' …' : ''}`
            : `Your tracked apps (${count})`}
        </p>
      </div>

      <FilterBar>
        <FilterBar.Search
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder="Search tracked apps..."
        />
        <FilterBar.Controls>
          <div className="flex w-full items-center rounded-lg border bg-background p-0.5 sm:inline-flex sm:w-auto">
            <PlatformTab active={platform === 'all'} onClick={() => setPlatform('all')}>
              All
            </PlatformTab>
            <PlatformTab active={platform === 'ios'} onClick={() => setPlatform('ios')}>
              <AppStoreSvg className="h-4 w-4" />
              <span className="hidden sm:inline">App Store</span>
            </PlatformTab>
            <PlatformTab active={platform === 'android'} onClick={() => setPlatform('android')}>
              <GooglePlaySvg className="h-4 w-4" />
              <span className="hidden sm:inline">Google Play</span>
            </PlatformTab>
          </div>
        </FilterBar.Controls>
      </FilterBar>

      {isPending || apps === undefined ? (
        <div className="grid gap-4 md:grid-cols-2">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-28 w-full" />
          ))}
        </div>
      ) : apps.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2">
          {apps.map((app) => (
            <AppCard key={app.id} app={app} />
          ))}
        </div>
      ) : (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Smartphone className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            {hasFilters
              ? 'No apps match your filters.'
              : 'No apps tracked yet. Go to Discover to find and track apps.'}
          </p>
        </div>
      )}
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
  children: ReactNode
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
