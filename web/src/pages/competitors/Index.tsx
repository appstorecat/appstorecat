import { type ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { keepPreviousData } from '@tanstack/react-query'
import { Users } from 'lucide-react'
import { useListAllCompetitors } from '@/api/endpoints/apps/apps'
import type { CompetitorGroupResource } from '@/api/models'
import type { ListAllCompetitorsPlatform } from '@/api/models/listAllCompetitorsPlatform'
import AppCard from '@/components/AppCard'
import QueryError from '@/components/QueryError'
import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import FilterBar from '@/components/FilterBar'
import { useDebounce } from '@/hooks/use-debounce'

type PlatformFilter = 'all' | 'ios' | 'android'

function parsePlatform(value: string | null): PlatformFilter {
  return value === 'ios' || value === 'android' ? value : 'all'
}

export default function CompetitorsIndex() {
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

  const trimmedSearch = debouncedSearch.trim()
  const { data, isPending, isError, refetch } = useListAllCompetitors(
    {
      platform: platform === 'all' ? undefined : (platform as ListAllCompetitorsPlatform),
      search: trimmedSearch.length > 0 ? trimmedSearch : undefined,
    },
    { query: { placeholderData: keepPreviousData } },
  )

  const groups: CompetitorGroupResource[] = data ?? []

  if (isPending) {
    return (
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Skeleton className="h-16 w-64" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load competitors." onRetry={() => refetch()} />
  }

  const totalCompetitors = groups.reduce((sum, g) => sum + g.competitors.length, 0)
  const hasFilters = platform !== 'all' || debouncedSearch.trim().length > 0

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Competitors</h1>
        <p className="text-sm text-muted-foreground">
          {totalCompetitors} competitor{totalCompetitors === 1 ? '' : 's'} across{' '}
          {groups.length} tracked app{groups.length === 1 ? '' : 's'}
        </p>
      </div>

      <FilterBar>
        <FilterBar.Search
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder="Search competitors..."
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

      {groups.length === 0 ? (
        <div className="rounded-xl border border-dashed p-12 text-center">
          <Users className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            {hasFilters
              ? 'No competitors match your filters.'
              : "No competitors yet. Add competitors from an app's Competitors tab."}
          </p>
        </div>
      ) : (
        <div className="space-y-8">
          {groups.map((group) => (
            <ParentGroup key={group.parent.id} group={group} />
          ))}
        </div>
      )}
    </div>
  )
}

function ParentGroup({ group }: { group: CompetitorGroupResource }) {
  const { parent, competitors } = group

  return (
    <section className="space-y-3">
      <Link
        to={`/apps/${parent.platform}/${parent.external_id}`}
        className="group flex items-center gap-3 rounded-lg px-1 py-1 transition-colors hover:bg-accent/40"
      >
        {parent.icon_url ? (
          <img
            src={parent.icon_url}
            alt={parent.name}
            className="h-9 w-9 shrink-0 rounded-lg"
          />
        ) : (
          <div className="h-9 w-9 shrink-0 rounded-lg bg-muted" />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <h2 className="truncate text-base font-semibold group-hover:underline">
              {parent.name}
            </h2>
            <Badge variant="secondary" className="shrink-0 text-[10px]">
              {competitors.length} competitor{competitors.length === 1 ? '' : 's'}
            </Badge>
          </div>
          <p className="truncate text-xs text-muted-foreground">
            {parent.publisher?.name ?? '—'}
          </p>
        </div>
      </Link>

      <div className="ml-2 space-y-2 border-l border-border pl-3 sm:ml-5 sm:pl-5">
        <div className="grid gap-3 md:grid-cols-2">
          {competitors.map((c) => (
            <AppCard key={c.id} app={c.app} />
          ))}
        </div>
      </div>
    </section>
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
