import { useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Smartphone, Info } from 'lucide-react'

interface Screenshot {
  url: string
  device_type: string
  order: number
}

interface StoreListingData {
  id: number
  version_id: number | null
  locale: string
  title: string
  subtitle: string | null
  description: string
  whats_new: string | null
  icon_url: string | null
  screenshots: Screenshot[]
  video_url: string | null
  fetched_at: string
  is_available?: boolean
}

interface AppVersionData {
  id: number
  version: string
  release_date: string | null
  whats_new: string | null
  file_size_bytes: number | null
  created_at: string
}

interface StoreListingTabProps {
  listings: StoreListingData[]
  versions: AppVersionData[]
  platform: string
  externalId: string
  selectedLocale: string
  selectedCountry?: string
  selectedVersion: string
}

export default function StoreListingTab({ listings, versions, platform, externalId, selectedLocale, selectedCountry = 'us', selectedVersion }: StoreListingTabProps) {
  const sortedVersions = useMemo(
    () => [...versions].sort((a, b) => b.id - a.id),
    [versions],
  )

  const latestVersionId = sortedVersions.length > 0 ? sortedVersions[0].id : null
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

  const currentListing = requestedListing ?? filteredListings[0]
  const isUnavailable = !!requestedListing && requestedListing.is_available === false
  const isFallback = !requestedListing && !!currentListing

  if (listings.length === 0) {
    return (
      <div className="py-12 text-center">
        <Smartphone className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No listings available.</p>
      </div>
    )
  }

  const localeLabel = selectedLocale || 'this locale'
  const dim = isUnavailable ? 'opacity-60 pointer-events-none select-none' : ''

  return (
    <div className="space-y-3">
      {isFallback && (
        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Info className="h-3.5 w-3.5" />
          <span>
            No listing captured for <span className="font-medium text-foreground">{selectedLocale}</span> yet — showing the closest available locale.
          </span>
        </div>
      )}

      {currentListing && (
        <div className={`space-y-5 ${dim}`}>
          {/* Title + Subtitle */}
          <div className="flex items-start gap-3">
            {currentListing.icon_url && (
              <img
                src={currentListing.icon_url}
                alt={currentListing.title}
                className="h-12 w-12 shrink-0 rounded-xl"
              />
            )}
            <div className="flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <h3 className="text-lg font-semibold leading-tight">{currentListing.title}</h3>
                {isUnavailable && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-amber-300/60 bg-amber-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    Not localized for {localeLabel}
                  </span>
                )}
              </div>
              {currentListing.subtitle && (
                <p className="mt-0.5 text-sm text-muted-foreground">{currentListing.subtitle}</p>
              )}
              {isUnavailable && (
                <p className="mt-1 text-xs text-muted-foreground">
                  The publisher hasn't localized this listing. Values below are cloned from the origin storefront for reference.
                </p>
              )}
            </div>
          </div>

          {/* Screenshots */}
          {currentListing.screenshots && currentListing.screenshots.length > 0 && (
            <Card>
              <CardHeader className="pb-3">
                <CardTitle className="text-sm font-medium">
                  Screenshots ({currentListing.screenshots.length})
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="flex gap-3 overflow-x-auto pb-2">
                  {currentListing.screenshots.map((screenshot, i) => (
                    <img
                      key={i}
                      src={screenshot.url}
                      alt={`Screenshot ${i + 1}`}
                      className="h-[280px] rounded-xl object-contain"
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
