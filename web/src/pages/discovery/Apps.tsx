import { useState } from 'react'
import { useDebounce } from '@/hooks/use-debounce'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import {
  getListAppsQueryKey,
  getSearchAppsQueryKey,
  useSearchApps,
  useTrackApp,
  useUntrackApp,
} from '@/api/endpoints/apps/apps'
import {
  type AppSearchResultResource,
  type AppSearchResultResourcePlatform,
  SearchAppsPlatform,
} from '@/api/models'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import PlatformSwitcher from '@/components/PlatformSwitcher'
import CountrySelect from '@/components/CountrySelect'
import FilterBar from '@/components/FilterBar'
import { Search, Smartphone, BookmarkPlus, BookmarkMinus, Loader2, Star } from 'lucide-react'

export default function DiscoveryApps() {
  const queryClient = useQueryClient()
  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebounce(searchTerm)
  const [platform, setPlatform] = useState<AppSearchResultResourcePlatform>(SearchAppsPlatform.ios)
  const [countryCode, setCountryCode] = useState<string>('us')
  const [tracking, setTracking] = useState<Set<string>>(new Set())

  const { data: searchResults, isFetching: searching } = useSearchApps(
    { term: debouncedSearch, platform, country_code: countryCode },
    { query: { enabled: debouncedSearch.length >= 2 } },
  )

  const trackMutation = useTrackApp()
  const untrackMutation = useUntrackApp()

  const invalidateAfterToggle = () => {
    queryClient.invalidateQueries({ queryKey: getSearchAppsQueryKey() })
    queryClient.invalidateQueries({ queryKey: getListAppsQueryKey() })
  }

  const toggleTrack = async (app: AppSearchResultResource) => {
    setTracking((prev) => new Set(prev).add(app.external_id))
    try {
      if (app.is_tracked) {
        await untrackMutation.mutateAsync({ platform, externalId: app.external_id })
      } else {
        await trackMutation.mutateAsync({ platform, externalId: app.external_id })
      }
      invalidateAfterToggle()
    } finally {
      setTracking((prev) => {
        const next = new Set(prev)
        next.delete(app.external_id)
        return next
      })
    }
  }

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Discover Apps</h1>
        <p className="text-sm text-muted-foreground">Search app stores and start tracking</p>
      </div>

      <FilterBar>
        <FilterBar.Search
          value={searchTerm}
          onChange={setSearchTerm}
          placeholder="Search apps in stores..."
        />
        <FilterBar.Controls>
          <PlatformSwitcher
            value={platform}
            onChange={(value) => setPlatform(value as AppSearchResultResourcePlatform)}
          />
          <CountrySelect value={countryCode} onChange={setCountryCode} className="w-full sm:w-[180px]" />
        </FilterBar.Controls>
      </FilterBar>

      {searchTerm.length < 2 && (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Search className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            Search for apps by name to discover and track them
          </p>
        </div>
      )}

      {searchTerm.length >= 2 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">
            {searching ? 'Searching...' : 'Store Results'}
          </h2>
          {searchResults && searchResults.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2">
              {searchResults.map((app) => (
                <Link
                  key={app.external_id}
                  to={`/apps/${platform}/${app.external_id}`}
                  className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm"
                >
                  {app.icon_url ? (
                    <img src={app.icon_url} alt={app.name} className="h-14 w-14 shrink-0 rounded-xl" />
                  ) : (
                    <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-muted">
                      <Smartphone className="h-5 w-5 text-muted-foreground" />
                    </div>
                  )}
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <p className="truncate text-sm font-semibold">{app.name}</p>
                      <div className="ml-auto shrink-0">
                        <Button
                          variant={app.is_tracked ? 'outline' : 'default'}
                          size="sm"
                          className="h-7 text-xs"
                          onClick={(e: React.MouseEvent) => {
                            e.preventDefault()
                            e.stopPropagation()
                            toggleTrack(app)
                          }}
                          disabled={tracking.has(app.external_id)}
                        >
                          {tracking.has(app.external_id) ? (
                            <Loader2 className="h-3 w-3 animate-spin" />
                          ) : app.is_tracked ? (
                            <>
                              <BookmarkMinus className="mr-1 h-3 w-3" /> Untrack
                            </>
                          ) : (
                            <>
                              <BookmarkPlus className="mr-1 h-3 w-3" /> Track
                            </>
                          )}
                        </Button>
                      </div>
                    </div>
                    <p className="truncate text-xs text-muted-foreground">
                      {app.publisher?.name ?? app.publisher_name ?? '—'}
                    </p>
                    <div className="mt-1 flex items-center gap-2">
                      {app.category && (
                        <Badge variant="secondary" className="text-[10px]">
                          {app.category.name}
                        </Badge>
                      )}
                      {app.rating && (
                        <span className="flex items-center gap-0.5 text-[10px] text-muted-foreground">
                          <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
                          {app.rating.toFixed(1)}
                        </span>
                      )}
                      {app.version && (
                        <span className="text-[10px] text-muted-foreground/60">
                          v{app.version}
                        </span>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            !searching && <p className="text-sm text-muted-foreground">No apps found.</p>
          )}
        </div>
      )}
    </div>
  )
}
