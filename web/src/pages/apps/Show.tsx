import { useState } from 'react'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import CountrySelect, { useCountries } from '@/components/CountrySelect'
import LanguageSelect from '@/components/LanguageSelect'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams, useSearchParams } from 'react-router-dom'
import axios from '@/lib/axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
import QueryError from '@/components/QueryError'
import SyncingOverlay from '@/components/SyncingOverlay'
import PartialSyncBanner from '@/components/PartialSyncBanner'
import { useSyncStatus } from '@/hooks/useSyncStatus'

function formatBytes(bytes: number | null): string {
  if (!bytes) return ''
  const mb = bytes / (1024 * 1024)
  return `${mb.toFixed(1)} MB`
}

function formatRatingCount(count: number | null): string {
  if (!count) return ''
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}

const IosSvg = AppStoreSvg
const AndroidSvg = GooglePlaySvg

export default function AppsShow() {
  const { platform, externalId } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const [tracking, setTracking] = useState(false)

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

  const { data: app, isLoading, isError, refetch } = useQuery({
    queryKey: ['apps', platform, externalId],
    queryFn: () => axios.get(`/apps/${platform}/${externalId}`).then((r) => r.data),
  })

  const { data: syncStatus } = useSyncStatus(platform, externalId, { enabled: !!app })
  const isSyncing = syncStatus?.status === 'queued' || syncStatus?.status === 'processing'

  const { data: competitorAppsForCompare } = useQuery({
    queryKey: ['competitor-apps', platform, externalId],
    queryFn: () => axios.get(`/apps/${platform}/${externalId}/competitors`).then((r) => (r.data ?? []).map((c: { app: { id: number; name: string; icon_url: string | null } }) => ({ id: c.app.id, name: c.app.name, icon_url: c.app.icon_url }))),
    enabled: !!platform && !!externalId && app?.is_tracked === true,
  })

  const toggleTrack = async () => {
    if (!app) return
    setTracking(true)
    try {
      if (app.is_tracked) {
        await axios.delete(`/apps/${platform}/${externalId}/track`)
      } else {
        await axios.post(`/apps/${platform}/${externalId}/track`)
      }
      queryClient.invalidateQueries({ queryKey: ['apps', platform, externalId] })
      queryClient.removeQueries({ queryKey: ['competitor-apps', platform, externalId] })
      queryClient.invalidateQueries({ queryKey: ['keywords-compare'] })
    } catch {
      // ignore
    } finally {
      setTracking(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load app details." onRetry={() => refetch()} />
  }

  if (!app) return null

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      {/* App Header */}
      <div className="flex items-start gap-4 sm:gap-5">
        <div className="relative shrink-0">
          {app.icon_url ? (
            <img
              src={app.icon_url}
              alt={app.name}
              className={`h-16 w-16 rounded-2xl shadow-md sm:h-24 sm:w-24 sm:rounded-[20px] ${app.is_available === false ? 'opacity-70' : ''}`}
            />
          ) : (
            <div className={`flex h-16 w-16 items-center justify-center rounded-2xl bg-muted shadow-md sm:h-24 sm:w-24 sm:rounded-[20px] ${app.is_available === false ? 'opacity-50' : ''}`}>
              {app.platform === 'ios' ? (
                <IosSvg className="h-7 w-7 text-muted-foreground sm:h-10 sm:w-10" />
              ) : (
                <AndroidSvg className="h-7 w-7 text-muted-foreground sm:h-10 sm:w-10" />
              )}
            </div>
          )}
          {app.is_available === false && (
            <div className="absolute inset-x-0 bottom-0 rounded-b-2xl bg-red-900/80 py-0.5 text-center text-[9px] font-semibold text-red-200 sm:rounded-b-[20px] sm:py-1 sm:text-[10px]">
              Removed
            </div>
          )}
        </div>

        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <div className="flex items-center gap-2">
            <h1 className="truncate text-lg font-bold sm:text-2xl">{app.name}</h1>
            {app.platform === 'ios' ? <IosSvg /> : <AndroidSvg />}
          </div>

          <p className="text-sm text-muted-foreground">
            {app.publisher?.name || '\u2014'}
          </p>

          <div className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-muted-foreground [&>span:not(:first-child)]:before:mr-1.5 [&>span:not(:first-child)]:before:content-['·']">
            {app.category?.name && <span>{app.category.name}</span>}
            {app.rating && Number(app.rating) > 0 ? (
              <span className="flex items-center gap-0.5">
                <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
                {Number(app.rating).toFixed(1)}
                {app.rating_count && (
                  <span className="text-muted-foreground/50">
                    ({formatRatingCount(app.rating_count)})
                  </span>
                )}
              </span>
            ) : (
              <span className="text-muted-foreground/50">No Ratings</span>
            )}
            {app.version && <span>v{app.version}</span>}
            {app.file_size_bytes && <span>{formatBytes(app.file_size_bytes)}</span>}
            {app.content_rating && <span>{app.content_rating}</span>}
            <span>
              <Badge variant="outline" className="text-[10px]">
                {app.is_free ? 'Free' : 'Paid'}
              </Badge>
            </span>
          </div>
        </div>

        <div className="flex shrink-0 flex-col items-end justify-between sm:min-h-24">
          <div className="flex items-center gap-2">
            <Button
              variant={app.is_tracked ? 'outline' : 'default'}
              size="sm"
              onClick={toggleTrack}
              disabled={tracking}
            >
              {tracking ? (
                <Loader2 className="mr-1 h-4 w-4 animate-spin" />
              ) : app.is_tracked ? (
                <BookmarkMinus className="mr-1 h-4 w-4" />
              ) : (
                <BookmarkPlus className="mr-1 h-4 w-4" />
              )}
              {app.is_tracked ? 'Untrack' : 'Track'}
            </Button>

            <DropdownMenu>
              <DropdownMenuTrigger render={<Button variant="ghost" size="icon" />}>
                <Ellipsis className="h-5 w-5" />
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem
                  render={<a href={app.platform === 'ios' ? `https://apps.apple.com/app/id${app.external_id}` : `https://play.google.com/store/apps/details?id=${app.external_id}`} target="_blank" rel="noopener noreferrer" />}
                >
                  <ExternalLink className="h-4 w-4" />
                  {app.platform === 'ios' ? 'App Store' : 'Play Store'}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          {(app.listings?.length > 0 || app.versions?.length > 0) && (() => {
            const currentListing = (app.listings ?? []).find((l: { locale: string }) => l.locale === effectiveLocale) as
              | { locale: string; is_available?: boolean }
              | undefined
            const isLocaleUnavailable = !!currentListing && currentListing.is_available === false

            return (
              <div className="flex flex-col gap-1.5">
                <div className="flex items-center gap-2">
                  <CountrySelect value={selectedCountry} onChange={setSelectedCountry} className="w-[160px]" />
                  {localesForCountry.length > 1 && (
                    <LanguageSelect
                      languages={localesForCountry}
                      value={effectiveLocale}
                      onChange={setSelectedLocale}
                      className="w-[150px]"
                    />
                  )}
                  {app.versions?.length > 0 && (
                    <Select value={selectedVersion} onValueChange={setSelectedVersion}>
                      <SelectTrigger className="w-[120px]">
                        <SelectValue>
                          {selectedVersion === 'latest'
                            ? `v${[...app.versions].sort((a: { id: number }, b: { id: number }) => b.id - a.id)[0]?.version}`
                            : `v${app.versions.find((v: { id: number }) => String(v.id) === selectedVersion)?.version ?? ''}`}
                        </SelectValue>
                      </SelectTrigger>
                      <SelectContent>
                        {[...app.versions].sort((a: { id: number }, b: { id: number }) => b.id - a.id).map((v: { id: number; version: string }, i: number) => (
                          <SelectItem key={v.id} value={i === 0 ? 'latest' : String(v.id)}>
                            {i === 0 ? `Latest (v${v.version})` : `v${v.version}`}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                  {isLocaleUnavailable && activeTab === 'store_listing' && (
                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">
                      Not localized
                    </span>
                  )}
                </div>
              </div>
            )
          })()}
        </div>
      </div>

      {/* Sync overlay: blocks tabs while queued/processing */}
      {isSyncing && syncStatus && (
        <SyncingOverlay status={syncStatus} />
      )}

      {/* Tabs — only show when app has data AND no active sync */}
      {!isSyncing && (app.listings?.length > 0 || app.versions?.length > 0) && (
        <>
          {/* Tab bar */}
          <div className="-mx-4 overflow-x-auto px-4">
            <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
              {([
                ['store_listing', 'Store Listing'],
                ['competitors', 'Competitors'],
                ['keywords', 'Keyword Density'],
                ['rankings', 'Rankings'],
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
                    listings={app.listings ?? []}
                    versions={app.versions ?? []}
                    platform={app.platform}
                    externalId={app.external_id}
                    selectedLocale={effectiveLocale}
                    selectedCountry={selectedCountry}
                    selectedVersion={selectedVersion}
                  />
                )}
                {activeTab === 'competitors' && (
                  <CompetitorsTab
                    competitors={app.competitors ?? []}
                    platform={app.platform}
                    externalId={app.external_id}
                    isTracked={app.is_tracked}
                    onTrack={toggleTrack}
                    trackLoading={tracking}
                  />
                )}
                {activeTab === 'keywords' && (
                  <KeywordsTab
                    platform={app.platform}
                    externalId={app.external_id}
                    versions={app.versions ?? []}
                    selectedLocale={effectiveLocale}
                    selectedVersion={selectedVersion}
                    allApps={competitorAppsForCompare ?? []}
                  />
                )}
                {activeTab === 'rankings' && (
                  <RankingsTab
                    platform={app.platform}
                    externalId={app.external_id}
                    selectedCountry={selectedCountry}
                  />
                )}
                {activeTab === 'changes' && (
                  <ChangesTab
                    listings={app.listings ?? []}
                    versions={app.versions ?? []}
                  />
                )}
                {activeTab === 'versions' && (
                  <VersionsTab versions={app.versions ?? []} />
                )}
              </>
            )}
          </div>
        </>
      )}
    </div>
  )
}
