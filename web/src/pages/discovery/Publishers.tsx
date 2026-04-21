import { useState } from 'react'
import { useDebounce } from '@/hooks/use-debounce'
import { Link } from 'react-router-dom'
import { useSearchPublishers } from '@/api/endpoints/publishers/publishers'
import { SearchPublishersPlatform } from '@/api/models/searchPublishersPlatform'
import { Input } from '@/components/ui/input'
import PlatformSwitcher from '@/components/PlatformSwitcher'
import CountrySelect from '@/components/CountrySelect'
import { Search, Building2 } from 'lucide-react'

export default function DiscoveryPublishers() {
  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebounce(searchTerm)
  const [platform, setPlatform] = useState<string>('ios')
  const [countryCode, setCountryCode] = useState<string>('us')

  const { data: results, isFetching: searching } = useSearchPublishers(
    {
      term: debouncedSearch,
      platform: platform as SearchPublishersPlatform,
      country_code: countryCode,
    },
    {
      query: { enabled: debouncedSearch.length >= 2 },
    },
  )

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Discover Publishers</h1>
        <p className="text-sm text-muted-foreground">Search for publishers across app stores</p>
      </div>

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
        <CountrySelect value={countryCode} onChange={setCountryCode} className="w-[180px]" />
      </div>

      {searchTerm.length < 2 && (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Building2 className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            Search for publishers by name to explore their apps
          </p>
        </div>
      )}

      {searchTerm.length >= 2 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">
            {searching ? 'Searching...' : 'Results'}
          </h2>
          {results && results.length > 0 ? (
            <div className="grid gap-4 md:grid-cols-2">
              {results.map((pub) => (
                <Link
                  key={pub.external_id}
                  to={`/publishers/${pub.platform}/${pub.external_id}?name=${encodeURIComponent(pub.name)}`}
                  className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm"
                >
                  {pub.sample_apps?.[0]?.icon_url ? (
                    <img src={pub.sample_apps[0].icon_url} alt="" className="h-12 w-12 shrink-0 rounded-xl" />
                  ) : (
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-muted">
                      <Building2 className="h-5 w-5 text-muted-foreground" />
                    </div>
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">{pub.name}</p>
                    <p className="text-xs text-muted-foreground">
                      {pub.app_count ?? 0} app{pub.app_count !== 1 ? 's' : ''} found
                    </p>
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            !searching && <p className="text-sm text-muted-foreground">No publishers found.</p>
          )}
        </div>
      )}
    </div>
  )
}
