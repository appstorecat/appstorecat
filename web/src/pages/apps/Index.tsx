import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search, Smartphone } from 'lucide-react'
import axios from '@/lib/axios'
import AppCard from '@/components/AppCard'
import QueryError from '@/components/QueryError'
import { Input } from '@/components/ui/input'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import { useDebounce } from '@/hooks/use-debounce'

type PlatformFilter = 'all' | 'ios' | 'android'

function parsePlatform(value: string | null): PlatformFilter {
  return value === 'ios' || value === 'android' ? value : 'all'
}

export default function AppsIndex() {
  const [searchParams, setSearchParams] = useSearchParams()

  const platform = parsePlatform(searchParams.get('platform'))
  const urlSearch = searchParams.get('search') ?? ''

  const [searchTerm, setSearchTerm] = useState(urlSearch)
  const debouncedSearch = useDebounce(searchTerm)

  // Keep the URL in sync with the debounced search term.
  useEffect(() => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      const trimmed = debouncedSearch.trim()
      if (trimmed) next.set('search', trimmed)
      else next.delete('search')
      return next
    }, { replace: true })
  }, [debouncedSearch, setSearchParams])

  const setPlatform = (value: PlatformFilter) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value === 'all') next.delete('platform')
      else next.set('platform', value)
      return next
    }, { replace: true })
  }

  const queryParams = useMemo(() => {
    const params: Record<string, string> = {}
    if (platform !== 'all') params.platform = platform
    if (debouncedSearch.trim().length > 0) params.search = debouncedSearch.trim()
    return params
  }, [platform, debouncedSearch])

  const { data: apps, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey: ['apps', platform, debouncedSearch],
    queryFn: () => axios.get('/apps', { params: queryParams }).then((r) => r.data),
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

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
            ? `${count} result${count === 1 ? '' : 's'} ${isFetching ? '…' : ''}`
            : `Your tracked apps (${count})`}
        </p>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[240px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search tracked apps..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9"
          />
        </div>

        <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
          <PlatformTab active={platform === 'all'} onClick={() => setPlatform('all')}>
            All
          </PlatformTab>
          <PlatformTab active={platform === 'ios'} onClick={() => setPlatform('ios')}>
            <AppStoreSvg className="h-4 w-4" />
            App Store
          </PlatformTab>
          <PlatformTab active={platform === 'android'} onClick={() => setPlatform('android')}>
            <GooglePlaySvg className="h-4 w-4" />
            Google Play
          </PlatformTab>
        </div>
      </div>

      {apps && apps.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2">
          {apps.map((app: Record<string, unknown>) => (
            <AppCard key={app.id as number} app={app as never} />
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
      className={`inline-flex cursor-pointer items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
        active
          ? 'bg-accent text-accent-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  )
}
