import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import axios from '@/lib/axios'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import PlatformSwitcher, { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import CountrySelect from '@/components/CountrySelect'
import { Search, Smartphone } from 'lucide-react'
import QueryError from '@/components/QueryError'

interface PublisherData {
  id: number
  name: string
  external_id: string | null
  platform: string
  url: string | null
  apps_count: number
}

interface SearchResult {
  external_id: string
  name: string
  url: string | null
  platform: string
  app_count: number
  sample_apps: { name: string; icon_url: string | null }[]
}

function PlatformIcon({ platform, className }: { platform: string; className?: string }) {
  return platform === 'ios' ? <AppStoreSvg className={className} /> : <GooglePlaySvg className={className} />
}

export default function PublishersIndex() {
  const [searchTerm, setSearchTerm] = useState('')
  const [platform, setPlatform] = useState<string>('ios')
  const [country, setCountry] = useState<string>('us')

  const { data: publishers, isLoading, isError, refetch } = useQuery<PublisherData[]>({
    queryKey: ['publishers'],
    queryFn: () => axios.get('/publishers').then((r) => r.data),
  })

  const { data: searchResults, isFetching: searching } = useQuery<SearchResult[]>({
    queryKey: ['developer-search', searchTerm, platform, country],
    queryFn: () =>
      axios.get('/publishers/search', { params: { term: searchTerm, platform, country } }).then((r) => r.data),
    enabled: searchTerm.length >= 2,
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load publishers." onRetry={() => refetch()} />
  }

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      <h1 className="text-2xl font-bold">Publishers</h1>

      {/* Search */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search publishers..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9"
          />
        </div>
        <PlatformSwitcher value={platform} onChange={setPlatform} />
        <CountrySelect value={country} onChange={setCountry} className="w-[180px]" />
      </div>

      {/* Search results */}
      {searchTerm.length >= 2 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">
            {searching ? 'Searching...' : 'Store Results'}
          </h2>
          {searchResults && searchResults.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2">
              {searchResults.map((dev) => (
                  <Link key={dev.external_id} to={`/publishers/${dev.platform}/${dev.external_id}?name=${encodeURIComponent(dev.name)}`}>
                    <div className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm">
                      {/* Sample app icons */}
                      <div className="flex h-14 w-14 shrink-0 items-center justify-center">
                        {dev.sample_apps.length > 0 && dev.sample_apps[0].icon_url ? (
                          <img
                            src={dev.sample_apps[0].icon_url}
                            alt=""
                            className="h-14 w-14 rounded-xl"
                          />
                        ) : (
                          <div className="flex h-14 w-14 items-center justify-center rounded-xl bg-muted">
                            <PlatformIcon platform={dev.platform} className="h-7 w-7 text-muted-foreground" />
                          </div>
                        )}
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                          <p className="truncate text-sm font-medium">{dev.name}</p>
                          <div className="ml-auto flex shrink-0 items-center gap-1.5">
                            <PlatformIcon platform={dev.platform} />
                          </div>
                        </div>
                        <p className="text-xs text-muted-foreground">
                          {dev.app_count} app{dev.app_count !== 1 ? 's' : ''} found
                        </p>
                        {dev.sample_apps.length > 1 && (
                          <div className="mt-1.5 flex items-center gap-1">
                            {dev.sample_apps.slice(1).map((app, i) => (
                              <div key={i} title={app.name}>
                                {app.icon_url ? (
                                  <img src={app.icon_url} alt="" className="h-5 w-5 rounded" />
                                ) : (
                                  <Smartphone className="h-5 w-5 text-muted-foreground/50" />
                                )}
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    </div>
                  </Link>
              ))}
            </div>
          ) : (
            !searching && <p className="text-sm text-muted-foreground">No publishers found.</p>
          )}
        </div>
      )}

      {/* My publishers */}
      {publishers && publishers.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">My Publishers</h2>
          <div className="grid gap-4 md:grid-cols-2">
            {publishers.map((dev) => (
              <Link key={dev.id} to={`/publishers/${dev.platform}/${dev.external_id}`}>
                <div className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm">
                  <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-muted">
                    <PlatformIcon platform={dev.platform} className="h-7 w-7 text-muted-foreground" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <p className="truncate text-sm font-medium">{dev.name}</p>
                      <div className="ml-auto flex shrink-0 items-center gap-1.5">
                        <PlatformIcon platform={dev.platform} />
                        <Badge variant="secondary" className="text-[10px]">
                          {dev.apps_count} app{dev.apps_count !== 1 ? 's' : ''}
                        </Badge>
                      </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      {dev.platform === 'ios' ? 'iOS' : 'Android'} Publisher
                    </p>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Empty state */}
      {(!publishers || publishers.length === 0) && searchTerm.length < 2 && (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Smartphone className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            No publishers yet. Search above to find publishers and add their apps.
          </p>
        </div>
      )}
    </div>
  )
}
