import { useMemo, type ReactNode } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Smartphone, Ban, Languages } from 'lucide-react'
import type { ListingResource } from '@/api/models/listingResource'
import type { VersionResource } from '@/api/models/versionResource'

interface StoreListingTabProps {
  listings: ListingResource[]
  versions: VersionResource[]
  platform: string
  externalId: string
  selectedLocale: string
  selectedCountry?: string
  selectedCountryName?: string
  selectedVersion: string
  unavailableCountries?: string[]
  controls?: ReactNode
}

export default function StoreListingTab({
  listings,
  versions,
  platform,
  externalId,
  selectedLocale,
  selectedCountry = 'us',
  selectedCountryName,
  selectedVersion,
  unavailableCountries = [],
  controls,
}: StoreListingTabProps) {
  // Backend returns versions newest-first (App::versions() relation orders by id desc).
  const latestVersionId = versions.length > 0 ? versions[0].id : null
  const selectedVersionId = selectedVersion

  const filteredListings = useMemo(() => {
    const vid = selectedVersionId === 'latest' ? latestVersionId : Number(selectedVersionId)
    if (!vid) return listings
    return listings.filter((l) => l.version_id === vid)
  }, [selectedVersionId, latestVersionId, listings])

  const requestedListing = useMemo(
    () => filteredListings.find((l) => l.locale === selectedLocale),
    [filteredListings, selectedLocale],
  )

  const isCountryUnavailable = unavailableCountries.includes(selectedCountry)
  const isLocaleMissing = !requestedListing
  const hideContent = isCountryUnavailable || isLocaleMissing
  const currentListing = hideContent ? undefined : requestedListing

  if (listings.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
        <Smartphone className="mb-4 h-10 w-10 text-muted-foreground" />
        <p className="text-sm font-medium">No listings captured yet</p>
        <p className="mt-1 max-w-xs text-xs text-muted-foreground">
          Store listings for this app will appear here after the next sync completes.
        </p>
      </div>
    )
  }

  const countryLabel = selectedCountryName || selectedCountry.toUpperCase()
  const storeName = platform === 'ios' ? 'App Store' : 'Play Store'

  return (
    <div className="space-y-5">
      {/* Title + Subtitle + Controls — always render controls (listing optional) */}
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-4">
        <div className="flex min-w-0 flex-1 items-start gap-3">
          {currentListing?.icon_url && (
            <img
              src={currentListing.icon_url}
              alt={currentListing.title}
              className="h-12 w-12 shrink-0 rounded-xl"
            />
          )}
          {currentListing && (
            <div className="min-w-0 flex-1">
              <h3 className="truncate text-lg font-semibold leading-tight">{currentListing.title}</h3>
              {currentListing.subtitle && (
                <p className="mt-0.5 truncate text-sm text-muted-foreground">{currentListing.subtitle}</p>
              )}
            </div>
          )}
        </div>
        {controls && (
          <div className="-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:shrink-0 lg:px-0 lg:pb-0 [&>*]:shrink-0">
            {controls}
          </div>
        )}
      </div>

      {hideContent && (
        <div className="overflow-hidden rounded-lg border border-amber-200/60 bg-amber-50/60 dark:border-amber-500/25 dark:bg-amber-500/5">
          <div className="flex items-start gap-3 px-4 py-3">
            <div className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
              {isCountryUnavailable ? <Ban className="h-4 w-4" /> : <Languages className="h-4 w-4" />}
            </div>
            <div className="flex-1 space-y-1">
              <p className="text-sm font-medium text-amber-900 dark:text-amber-200">
                {isCountryUnavailable
                  ? <>App not available in {countryLabel}</>
                  : <>No listing for <span className="rounded bg-amber-200/60 px-1 py-0.5 font-mono text-xs dark:bg-amber-500/20">{selectedLocale || 'this locale'}</span></>}
              </p>
              <p className="text-xs text-amber-800/80 dark:text-amber-300/80">
                {isCountryUnavailable
                  ? <>This app is not listed on the {storeName} in {countryLabel}.</>
                  : <>The app hasn't been localized into this locale yet.</>}
              </p>
            </div>
          </div>
        </div>
      )}

      {currentListing && (
        <div className="space-y-5">

          {/* Screenshots */}
          {currentListing.screenshots && currentListing.screenshots.length > 0 && (
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">
                  Screenshots ({currentListing.screenshots.length})
                </CardTitle>
              </CardHeader>
              <CardContent className="px-3 sm:px-6">
                <div className="-mx-3 flex gap-3 overflow-x-auto px-3 pb-2 sm:mx-0 sm:px-0 [-webkit-overflow-scrolling:touch]">
                  {currentListing.screenshots.map((screenshot, i) => (
                    <img
                      key={i}
                      src={screenshot.url}
                      alt={`Screenshot ${i + 1}`}
                      className="h-[220px] shrink-0 rounded-xl object-contain sm:h-[280px]"
                    />
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Description */}
          <Card>
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-sm font-medium">Description</CardTitle>
                <span className="text-xs text-muted-foreground">
                  {currentListing.description?.length ?? 0} chars
                </span>
              </div>
            </CardHeader>
            <CardContent>
              <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                {currentListing.description}
              </p>
            </CardContent>
          </Card>

          {/* What's New */}
          {currentListing.whats_new && (
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">What's New</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                  {currentListing.whats_new}
                </p>
              </CardContent>
            </Card>
          )}
        </div>
      )}
    </div>
  )
}
