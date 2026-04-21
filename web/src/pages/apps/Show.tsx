import type { ComponentProps } from 'react'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import CountrySelect, { useCountries } from '@/components/CountrySelect'
import LanguageSelect from '@/components/LanguageSelect'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useQueryClient } from '@tanstack/react-query'
import { useParams, useSearchParams } from 'react-router-dom'
import {
  getListAppsQueryKey,
  getListCompetitorsQueryKey,
  getShowAppQueryKey,
  useListCompetitors,
  useShowApp,
  useTrackApp,
  useUntrackApp,
} from '@/api/endpoints/apps/apps'
import type { AppDetailResource, VersionResource } from '@/api/models'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  ExternalLink,
  Star,
  Ellipsis,
  Loader2,
  BookmarkPlus,
  BookmarkMinus,
} from 'lucide-react'
import StoreListingTab from '@/components/tabs/StoreListingTab'
import ChangesTab from '@/components/tabs/ChangesTab'
import VersionsTab from '@/components/tabs/VersionsTab'
import CompetitorsTab from '@/components/tabs/CompetitorsTab'
import KeywordsTab from '@/components/tabs/KeywordsTab'
import RankingsTab from '@/components/tabs/RankingsTab'
import RatingsTab from '@/components/tabs/RatingsTab'
import QueryError from '@/components/QueryError'
import SyncingOverlay from '@/components/SyncingOverlay'
import PartialSyncBanner from '@/components/PartialSyncBanner'
import { useSyncStatus } from '@/hooks/useSyncStatus'

function formatBytes(bytes: number | null | undefined): string {
  if (!bytes) return ''
  const mb = bytes / (1024 * 1024)
  return `${mb.toFixed(1)} MB`
}

function formatRatingCount(count: number | null | undefined): string {
  if (!count) return ''
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}

const IosSvg = AppStoreSvg
const AndroidSvg = GooglePlaySvg

export default function AppsShow() {
  const { platform, externalId } = useParams<{ platform: 'ios' | 'android'; externalId: string }>()
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const activeTab = searchParams.get('tab') || 'store_listing'
  const setActiveTab = (tab: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (tab === 'store_listing') next.delete('tab')
      else next.set('tab', tab)
      return next
    }, { replace: true })
  }
  const { data: countries } = useCountries()

  const selectedCountry = searchParams.get('country_code') || 'us'
  const selectedLocale = searchParams.get('locale') || ''

  const currentCountry = countries?.find((c) => c.code === selectedCountry)
  const localesForCountry = (platform === 'ios' ? currentCountry?.ios_languages : currentCountry?.android_languages) ?? []
  const effectiveLocale = selectedLocale && localesForCountry.includes(selectedLocale)
    ? selectedLocale
    : localesForCountry[0] ?? ''

  const setSelectedCountry = (code: string) => {
    const country = countries?.find((c) => c.code === code)
    const locales = (platform === 'ios' ? country?.ios_languages : country?.android_languages) ?? []
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (code === 'us') next.delete('country_code')
      else next.set('country_code', code)
      if (locales.length > 0) next.set('locale', locales[0])
      else next.delete('locale')
      return next
    }, { replace: true })
  }

  const setSelectedLocale = (locale: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('locale', locale)
      return next
    }, { replace: true })
  }

  const selectedVersion = searchParams.get('version') || 'latest'
  const setSelectedVersion = (v: string | null) => {
    if (!v) return
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (v === 'latest') next.delete('version')
      else next.set('version', v)
      return next
    }, { replace: true })
  }

  const { data: app, isLoading, isError, refetch } = useShowApp(
    platform as 'ios' | 'android',
    externalId as string,
    { query: { enabled: !!platform && !!externalId } },
  )

  const { data: syncStatus } = useSyncStatus(platform, externalId, { enabled: !!app })
  const isSyncing = syncStatus?.status === 'queued' || syncStatus?.status === 'processing'

  const { data: competitorAppsForCompare } = useListCompetitors(
    platform as 'ios' | 'android',
    externalId as string,
    {
      query: {
        enabled: !!platform && !!externalId && app?.is_tracked === true,
        select: (competitors) =>
          competitors.map((c) => ({
            id: c.app.id,
            name: c.app.name,
            icon_url: c.app.icon_url ?? null,
          })),
      },
    },
  )

  const trackMutation = useTrackApp()
  const untrackMutation = useUntrackApp()
  const tracking = trackMutation.isPending || untrackMutation.isPending

  const toggleTrack = async () => {
    if (!app || !platform || !externalId) return
    try {
      if (app.is_tracked) {
        await untrackMutation.mutateAsync({ platform, externalId })
      } else {
        await trackMutation.mutateAsync({ platform, externalId })
      }
      queryClient.invalidateQueries({ queryKey: getShowAppQueryKey(platform, externalId) })
      queryClient.removeQueries({ queryKey: getListCompetitorsQueryKey(platform, externalId) })
      // Prefix-invalidate every list that can filter by is_tracked so the new
      // state propagates across Apps, Competitors, Search and Publisher views.
      queryClient.invalidateQueries({ queryKey: ['/apps'] })
      queryClient.invalidateQueries({ queryKey: ['/competitors'] })
      queryClient.invalidateQueries({ queryKey: ['/apps/search'] })
      queryClient.invalidateQueries({ queryKey: ['/publishers'] })
      queryClient.invalidateQueries({ queryKey: ['keywords-compare'] })
    } catch {
      // ignore
    }
  }

  if (isLoading) {
    return (
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex items-start gap-4">
          <Skeleton className="h-20 w-20 rounded-2xl" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-7 w-72" />
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-4 w-32" />
          </div>
          <Skeleton className="h-10 w-24" />
        </div>
        <Skeleton className="h-10 w-full max-w-lg" />
        <Skeleton className="h-64 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load app details." onRetry={() => refetch()} />
  }

  if (!app) return null

  // At runtime the backend always fills these fields; narrow here to avoid
  // repeatedly asserting them at each render site.
  const detail = app as Required<Pick<AppDetailResource, 'platform' | 'external_id'>> & AppDetailResource
  const versions = detail.versions ?? []
  const listings = detail.listings ?? []
  const hasListingsOrVersions = listings.length > 0 || versions.length > 0
  // Backend returns versions newest-first (App::versions() relation orders by id desc).
  const latestVersion = versions[0]
  const activeVersion: VersionResource | undefined =
    selectedVersion === 'latest'
      ? latestVersion
      : versions.find((v) => String(v.id) === selectedVersion)

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      {/* App Header */}
      <div className="flex items-start gap-4 sm:gap-5">
        <div className="relative shrink-0">
          {detail.icon_url ? (
            <img
              src={detail.icon_url}
              alt={detail.name}
              className={`h-16 w-16 rounded-2xl shadow-md sm:h-24 sm:w-24 sm:rounded-[20px] ${detail.is_available === false ? 'opacity-70' : ''}`}
            />
          ) : (
            <div className={`flex h-16 w-16 items-center justify-center rounded-2xl bg-muted shadow-md sm:h-24 sm:w-24 sm:rounded-[20px] ${detail.is_available === false ? 'opacity-50' : ''}`}>
              {detail.platform === 'ios' ? (
                <IosSvg className="h-7 w-7 text-muted-foreground sm:h-10 sm:w-10" />
              ) : (
                <AndroidSvg className="h-7 w-7 text-muted-foreground sm:h-10 sm:w-10" />
              )}
            </div>
          )}
          {detail.is_available === false && (
            <div className="absolute inset-x-0 bottom-0 rounded-b-2xl bg-red-900/80 py-0.5 text-center text-[9px] font-semibold text-red-200 sm:rounded-b-[20px] sm:py-1 sm:text-[10px]">
              Removed
            </div>
          )}
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <div className="flex items-center gap-2">
            <h1 className="truncate text-lg font-bold sm:text-2xl">{detail.name}</h1>
            {detail.platform === 'ios' ? <IosSvg /> : <AndroidSvg />}
          </div>

          <p className="text-sm text-muted-foreground">
            {detail.publisher?.name || '\u2014'}
          </p>

          <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground [&>span:not(:first-child)]:before:mr-1.5 [&>span:not(:first-child)]:before:content-['·']">
            {detail.category?.name && <span>{detail.category.name}</span>}
            {detail.rating && Number(detail.rating) > 0 ? (
              <span className="flex items-center gap-0.5">
                <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
                {Number(detail.rating).toFixed(1)}
                {detail.rating_count && (
                  <span className="text-muted-foreground/50">
                    ({formatRatingCount(detail.rating_count)})
                  </span>
                )}
              </span>
            ) : (
              <span className="text-muted-foreground/50">No Ratings</span>
            )}
            {detail.version && <span>v{detail.version}</span>}
            {detail.file_size_bytes && <span>{formatBytes(detail.file_size_bytes)}</span>}
            <span>
              <Badge variant="outline" className="text-[10px]">
                {detail.is_free ? 'Free' : 'Paid'}
              </Badge>
            </span>
          </div>
        </div>

        <div className="flex shrink-0 flex-col items-end justify-between sm:min-h-24">
          <div className="flex items-center gap-2">
            <Button
              variant={detail.is_tracked ? 'outline' : 'default'}
              size="sm"
              onClick={toggleTrack}
              disabled={tracking}
            >
              {tracking ? (
                <Loader2 className="mr-1 h-4 w-4 animate-spin" />
              ) : detail.is_tracked ? (
                <BookmarkMinus className="mr-1 h-4 w-4" />
              ) : (
                <BookmarkPlus className="mr-1 h-4 w-4" />
              )}
              {detail.is_tracked ? 'Untrack' : 'Track'}
            </Button>

            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="ghost" size="icon" />}>
                <Ellipsis className="h-5 w-5" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                  render={<a href={detail.platform === 'ios' ? `https://apps.apple.com/app/id${detail.external_id}` : `https://play.google.com/store/apps/details?id=${detail.external_id}`} target="_blank" rel="noopener noreferrer" />}
                >
                  <ExternalLink className="h-4 w-4" />
                  {detail.platform === 'ios' ? 'App Store' : 'Play Store'}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          {hasListingsOrVersions && (
            <div className="flex items-center gap-2">
              <CountrySelect
                value={selectedCountry}
                onChange={setSelectedCountry}
                className="w-[160px]"
                disabledCodes={detail.unavailable_countries ?? []}
              />
              {localesForCountry.length > 1 && (
                <LanguageSelect
                  languages={localesForCountry}
                  value={effectiveLocale}
                  onChange={setSelectedLocale}
                  className="w-[150px]"
                />
              )}
              {versions.length > 0 && (
                <Select value={selectedVersion} onValueChange={setSelectedVersion}>
                  <SelectTrigger className="w-[120px]">
                    <SelectValue>
                      {activeVersion ? `v${activeVersion.version}` : ''}
                    </SelectValue>
                  </SelectTrigger>
                  <SelectContent>
                    {versions.map((v, i) => (
                      <SelectItem key={v.id} value={i === 0 ? 'latest' : String(v.id)}>
                        {i === 0 ? `Latest (v${v.version})` : `v${v.version}`}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Sync overlay: blocks tabs while queued/processing */}
      {isSyncing && syncStatus && (
        <SyncingOverlay status={syncStatus} />
      )}

      {/* Tabs — only show when app has data AND no active sync */}
      {!isSyncing && hasListingsOrVersions && (
        <>
          {/* Tab bar */}
          <div className="-mx-4 overflow-x-auto px-4">
            <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
              {([
                ['store_listing', 'Store Listing'],
                ['competitors', 'Competitors'],
                ['keywords', 'Keyword Density'],
                ['rankings', 'Rankings'],
                ['ratings', 'Ratings'],
                ['changes', 'Changes'],
                ['versions', 'Versions'],
              ] as [string, string][]).map(([value, label]) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setActiveTab(value)}
                  className={`inline-flex cursor-pointer items-center rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                    activeTab === value
                      ? 'bg-accent text-accent-foreground shadow-sm'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          <div className="mt-1">
            {syncStatus && syncStatus.failed_items_count > 0 && (
              <div className="mb-3">
                <PartialSyncBanner status={syncStatus} />
              </div>
            )}
            {(
              <>
                {activeTab === 'store_listing' && (
                  <StoreListingTab
                    listings={listings}
                    versions={versions}
                    platform={detail.platform}
                    externalId={detail.external_id}
                    selectedLocale={effectiveLocale}
                    selectedCountry={selectedCountry}
                    selectedCountryName={currentCountry?.name}
                    selectedVersion={selectedVersion}
                    unavailableCountries={detail.unavailable_countries ?? []}
                  />
                )}
                {activeTab === 'competitors' && (
                  <CompetitorsTab
                    // CompetitorsTab still declares its own stricter inline `Competitor` type
                    // (with `last_build_at`); cast until that component migrates to CompetitorResource.
                    competitors={(detail.competitors ?? []) as unknown as ComponentProps<typeof CompetitorsTab>['competitors']}
                    platform={detail.platform}
                    externalId={detail.external_id}
                    isTracked={detail.is_tracked ?? false}
                    onTrack={toggleTrack}
                    trackLoading={tracking}
                  />
                )}
                {activeTab === 'keywords' && (
                  <KeywordsTab
                    platform={detail.platform}
                    externalId={detail.external_id}
                    versions={versions}
                    selectedLocale={effectiveLocale}
                    selectedVersion={selectedVersion}
                    allApps={competitorAppsForCompare ?? []}
                  />
                )}
                {activeTab === 'rankings' && (
                  <RankingsTab
                    platform={detail.platform}
                    externalId={detail.external_id}
                    selectedCountry={selectedCountry}
                  />
                )}
                {activeTab === 'ratings' && (
                  <RatingsTab
                    platform={detail.platform}
                    externalId={detail.external_id}
                  />
                )}
                {activeTab === 'changes' && (
                  <ChangesTab
                    listings={listings}
                    versions={versions}
                    platform={detail.platform}
                  />
                )}
                {activeTab === 'versions' && (
                  <VersionsTab versions={versions} />
                )}
              </>
            )}
          </div>
        </>
      )}
    </div>
  )
}
