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
  ExternalLink,
  Star,
  Loader2,
  BookmarkPlus,
  BookmarkMinus,
  FileText,
  Users as UsersIcon,
  Key as KeyIcon,
  Trophy,
  Star as StarIcon,
  History as HistoryIcon,
  GitCommit,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { useDominantColor, rgbToCss } from '@/hooks/useDominantColor'
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

function EstimateCard({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-1 flex-col items-start justify-center rounded-lg border border-white/20 bg-white/10 px-3 py-2 backdrop-blur-sm sm:flex-none sm:min-w-[120px] sm:items-end sm:rounded-xl sm:px-4 sm:py-3 sm:text-right">
      <span className="text-[9px] font-medium uppercase tracking-wider text-white/70 sm:text-[10px]">
        {label}
      </span>
      <span className="text-base font-semibold text-white sm:mt-1 sm:text-xl">
        {value}
      </span>
    </div>
  )
}

type TabConfig = {
  value: string
  label: string
  icon: LucideIcon
}

const TABS: TabConfig[] = [
  { value: 'store_listing', label: 'Store Listing', icon: FileText },
  { value: 'competitors', label: 'Competitors', icon: UsersIcon },
  { value: 'keywords', label: 'Keyword Density', icon: KeyIcon },
  { value: 'rankings', label: 'Rankings', icon: Trophy },
  { value: 'ratings', label: 'Ratings', icon: StarIcon },
  { value: 'changes', label: 'Changes', icon: HistoryIcon },
  { value: 'versions', label: 'Versions', icon: GitCommit },
]

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

  const dominant = useDominantColor(app?.icon_url)

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

  const storeUrl = detail.platform === 'ios'
    ? `https://apps.apple.com/app/id${detail.external_id}`
    : `https://play.google.com/store/apps/details?id=${detail.external_id}`

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      {/* App Header — dominant-color gradient */}
      <div className="-mx-4 -mt-4 overflow-hidden border-b border-border sm:mx-0 sm:mt-0 sm:rounded-2xl sm:border">
        <div
          className="relative px-4 pt-6 pb-5 sm:px-8 sm:pt-8 sm:pb-7"
          style={{
            background: dominant
              ? `linear-gradient(135deg, ${rgbToCss(dominant, 0.95)} 0%, ${rgbToCss(dominant, 0.55)} 50%, ${rgbToCss(dominant, 0.2)} 100%)`
              : 'linear-gradient(135deg, hsl(var(--muted)) 0%, hsl(var(--background)) 100%)',
          }}
        >
          <div
            aria-hidden
            className="pointer-events-none absolute inset-0"
            style={{ background: 'radial-gradient(60% 80% at 15% 20%, rgba(255,255,255,0.18) 0%, transparent 60%)' }}
          />

          <div className="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
            <div className="relative shrink-0">
              {detail.icon_url ? (
                <img
                  src={detail.icon_url}
                  alt={detail.name}
                  className={`h-20 w-20 rounded-2xl shadow-xl ring-1 ring-black/5 sm:h-28 sm:w-28 sm:rounded-[22px] ${detail.is_available === false ? 'opacity-70' : ''}`}
                  crossOrigin="anonymous"
                  referrerPolicy="no-referrer"
                />
              ) : (
                <div className={`flex h-20 w-20 items-center justify-center rounded-2xl bg-white/20 shadow-xl ring-1 ring-black/5 backdrop-blur-sm sm:h-28 sm:w-28 sm:rounded-[22px] ${detail.is_available === false ? 'opacity-50' : ''}`}>
                  {detail.platform === 'ios' ? (
                    <IosSvg className="h-8 w-8 text-white sm:h-12 sm:w-12" />
                  ) : (
                    <AndroidSvg className="h-8 w-8 text-white sm:h-12 sm:w-12" />
                  )}
                </div>
              )}
              {detail.is_available === false && (
                <div className="absolute inset-x-0 bottom-0 rounded-b-2xl bg-red-900/80 py-0.5 text-center text-[9px] font-semibold text-red-200 sm:rounded-b-[22px] sm:py-1 sm:text-[10px]">
                  Removed
                </div>
              )}
            </div>

            <div className="flex min-w-0 flex-1 flex-col gap-1">
              <div className="flex items-start gap-2">
                <h1 className="text-balance text-xl font-bold leading-tight text-white drop-shadow-sm sm:text-3xl">
                  {detail.name}
                </h1>
                <span className="mt-1 shrink-0 text-white/80 [&>svg]:h-4 [&>svg]:w-4 sm:[&>svg]:h-5 sm:[&>svg]:w-5">
                  {detail.platform === 'ios' ? <IosSvg /> : <AndroidSvg />}
                </span>
              </div>

              <p className="truncate text-sm font-medium text-white/85 sm:text-base">
                {detail.publisher?.name || '\u2014'}
              </p>

              <div className="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-white/70 [&>span:not(:first-child)]:before:mr-1.5 [&>span:not(:first-child)]:before:content-['·']">
                {detail.category?.name && <span>{detail.category.name}</span>}
                {detail.rating && Number(detail.rating) > 0 ? (
                  <span className="flex items-center gap-0.5">
                    <Star className="h-3 w-3 fill-yellow-300 text-yellow-300" />
                    {Number(detail.rating).toFixed(1)}
                    {detail.rating_count && (
                      <span className="text-white/55">
                        ({formatRatingCount(detail.rating_count)})
                      </span>
                    )}
                  </span>
                ) : (
                  <span className="text-white/55">No Ratings</span>
                )}
                {detail.version && <span>v{detail.version}</span>}
                {detail.file_size_bytes && <span>{formatBytes(detail.file_size_bytes)}</span>}
                <span>
                  <Badge variant="outline" className="border-white/40 bg-white/10 text-[10px] text-white">
                    {detail.is_free ? 'Free' : 'Paid'}
                  </Badge>
                </span>
              </div>

            </div>

            {/* 30-day estimates — stacked full-width on mobile, right-aligned on desktop */}
            <div className="flex w-full items-stretch gap-2 sm:w-auto sm:shrink-0 sm:gap-4">
              <EstimateCard label="Downloads (30d)" value="N/A" />
              <EstimateCard label="Revenue (30d)" value="N/A" />
            </div>
          </div>
        </div>
      </div>

      {/* Sync overlay: blocks tabs while queued/processing */}
      {isSyncing && syncStatus && (
        <SyncingOverlay status={syncStatus} />
      )}

      {/* Tabs — only show when app has data AND no active sync */}
      {!isSyncing && hasListingsOrVersions && (
        <div className="flex flex-col gap-4 lg:flex-row lg:gap-6">
          {/* Mobile/tablet: track + tab bar + store (last) */}
          <div className="flex flex-col gap-2 lg:hidden">
            <div className="flex items-center gap-2">
              <Button
                variant={detail.is_tracked ? 'outline' : 'default'}
                size="sm"
                onClick={toggleTrack}
                disabled={tracking}
                className="shrink-0 whitespace-nowrap"
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
              <div className="min-w-0 flex-1 overflow-x-auto">
                <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
                  {TABS.map((tab) => (
                    <button
                      key={tab.value}
                      type="button"
                      onClick={() => setActiveTab(tab.value)}
                      aria-label={tab.label}
                      title={tab.label}
                      className={`inline-flex shrink-0 cursor-pointer items-center gap-1.5 whitespace-nowrap rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors ${
                        activeTab === tab.value
                          ? 'bg-accent text-accent-foreground shadow-sm'
                          : 'text-muted-foreground hover:text-foreground'
                      }`}
                    >
                      <tab.icon className="h-4 w-4" />
                    </button>
                  ))}
                </div>
              </div>
              <a
                href={storeUrl}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={detail.platform === 'ios' ? 'Open App Store' : 'Open Play Store'}
                title={detail.platform === 'ios' ? 'Open App Store' : 'Open Play Store'}
                className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border text-foreground transition-colors hover:bg-accent"
              >
                <ExternalLink className="h-4 w-4" />
              </a>
            </div>
          </div>

          {/* Desktop: vertical tab sidebar */}
          <nav className="hidden w-48 shrink-0 flex-col gap-4 lg:flex">
            <Button
              variant={detail.is_tracked ? 'outline' : 'default'}
              onClick={toggleTrack}
              disabled={tracking}
              className="w-full justify-center"
            >
              {tracking ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : detail.is_tracked ? (
                <BookmarkMinus className="mr-2 h-4 w-4" />
              ) : (
                <BookmarkPlus className="mr-2 h-4 w-4" />
              )}
              {detail.is_tracked ? 'Untrack' : 'Track'}
            </Button>

            <ul className="flex flex-col gap-0.5">
              {TABS.map((tab) => {
                const active = activeTab === tab.value
                return (
                  <li key={tab.value}>
                    <button
                      type="button"
                      onClick={() => setActiveTab(tab.value)}
                      className={`group relative flex w-full cursor-pointer items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-colors ${
                        active
                          ? 'bg-accent font-medium text-accent-foreground'
                          : 'text-muted-foreground hover:bg-accent/40 hover:text-foreground'
                      }`}
                    >
                      {active && (
                        <span className="absolute left-0 top-1.5 bottom-1.5 w-0.5 rounded-r-sm bg-emerald-500" />
                      )}
                      <tab.icon className="h-4 w-4 shrink-0" />
                      <span className="truncate">{tab.label}</span>
                    </button>
                  </li>
                )
              })}

              <li className="mt-1 border-t border-border pt-2">
                <a
                  href={storeUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="group flex items-center gap-2.5 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent/40 hover:text-foreground"
                >
                  <ExternalLink className="h-4 w-4 shrink-0" />
                  <span className="truncate">
                    {detail.platform === 'ios' ? 'App Store' : 'Play Store'}
                  </span>
                </a>
              </li>
            </ul>
          </nav>

          {/* Tab content */}
          <div className="min-w-0 flex-1">
            {syncStatus && syncStatus.failed_items_count > 0 && (
              <div className="mb-3">
                <PartialSyncBanner status={syncStatus} />
              </div>
            )}
            {(() => {
              const listingControls = (
                <>
                  <CountrySelect
                    value={selectedCountry}
                    onChange={setSelectedCountry}
                    className="h-9 w-[160px]"
                    disabledCodes={detail.unavailable_countries ?? []}
                  />
                  {localesForCountry.length > 1 && (
                    <LanguageSelect
                      languages={localesForCountry}
                      value={effectiveLocale}
                      onChange={setSelectedLocale}
                      className="h-9 w-[150px]"
                    />
                  )}
                  {versions.length > 0 && (
                    <Select value={selectedVersion} onValueChange={setSelectedVersion}>
                      <SelectTrigger className="h-9 w-[120px]">
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
                </>
              )

              if (activeTab === 'store_listing') {
                return (
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
                    controls={listingControls}
                  />
                )
              }

              if (activeTab === 'keywords') {
                return (
                  <KeywordsTab
                    platform={detail.platform}
                    externalId={detail.external_id}
                    versions={versions}
                    selectedLocale={effectiveLocale}
                    selectedVersion={selectedVersion}
                    allApps={competitorAppsForCompare ?? []}
                    controls={listingControls}
                  />
                )
              }

              return null
            })()}
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
              />
            )}
            {activeTab === 'versions' && (
              <VersionsTab versions={versions} />
            )}
          </div>
        </div>
      )}
    </div>
  )
}
