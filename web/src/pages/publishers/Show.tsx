import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import {
  getPublisherStoreAppsQueryKey,
  getShowPublisherQueryKey,
  usePublisherStoreApps,
  useShowPublisher,
} from '@/api/endpoints/publishers/publishers'
import {
  getListAppsQueryKey,
  useTrackApp,
  useUntrackApp,
} from '@/api/endpoints/apps/apps'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { BookmarkMinus, BookmarkPlus, ExternalLink, Loader2, Plus, Smartphone, Star } from 'lucide-react'
import QueryError from '@/components/QueryError'
import AppCard from '@/components/AppCard'

function formatRatingCount(count: number | null | undefined): string {
  if (!count) return ''
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}

export default function PublishersShow() {
  const { platform, externalId } = useParams<{ platform: 'ios' | 'android'; externalId: string }>()
  const [searchParams] = useSearchParams()
  const nameParam = searchParams.get('name')
  const queryClient = useQueryClient()
  const [importing, setImporting] = useState<Set<string>>(new Set())
  const [importingAll, setImportingAll] = useState(false)

  const trackAppMutation = useTrackApp()
  const untrackAppMutation = useUntrackApp()

  const {
    data,
    isLoading,
    isError,
    refetch,
  } = useShowPublisher(
    platform as 'ios' | 'android',
    externalId ?? '',
    nameParam ? { name: nameParam } : undefined,
  )

  const { data: storeData, isLoading: loadingStore } = usePublisherStoreApps(
    platform as 'ios' | 'android',
    externalId ?? '',
    { query: { enabled: !!data?.publisher?.external_id } },
  )

  const invalidateAll = () => {
    queryClient.invalidateQueries({
      queryKey: getShowPublisherQueryKey(
        platform as 'ios' | 'android',
        externalId ?? '',
        nameParam ? { name: nameParam } : undefined,
      ),
    })
    queryClient.invalidateQueries({
      queryKey: getPublisherStoreAppsQueryKey(
        platform as 'ios' | 'android',
        externalId ?? '',
      ),
    })
    queryClient.invalidateQueries({ queryKey: getListAppsQueryKey() })
  }

  const toggleApp = async (extId: string, isTracked: boolean) => {
    if (!data || !platform) return
    setImporting((prev) => new Set(prev).add(extId))
    try {
      if (isTracked) {
        await untrackAppMutation.mutateAsync({ platform: platform as 'ios' | 'android', externalId: extId })
      } else {
        await trackAppMutation.mutateAsync({ platform: platform as 'ios' | 'android', externalId: extId })
      }
      invalidateAll()
    } finally {
      setImporting((prev) => {
        const next = new Set(prev)
        next.delete(extId)
        return next
      })
    }
  }

  const storeApps = storeData?.apps ?? []
  const untrackedCount = storeApps.filter((a) => !a.is_tracked).length

  const trackAll = async () => {
    if (!platform) return
    const untracked = storeApps.filter((a) => !a.is_tracked).map((a) => a.external_id)
    if (untracked.length === 0) return
    if (!confirm(`Track ${untracked.length} app${untracked.length > 1 ? 's' : ''}?`)) return

    setImportingAll(true)
    try {
      for (const extId of untracked) {
        await trackAppMutation.mutateAsync({ platform: platform as 'ios' | 'android', externalId: extId })
      }
      invalidateAll()
    } finally {
      setImportingAll(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex h-full flex-1 flex-col gap-6 p-4">
        <div className="flex items-center gap-4">
          <Skeleton className="h-16 w-16 rounded-xl" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-6 w-64" />
            <Skeleton className="h-4 w-40" />
          </div>
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-40 w-full" />
          <Skeleton className="h-40 w-full" />
        </div>
      </div>
    )
  }

  if (isError || !data || !data.publisher) {
    return <QueryError message="Failed to load publisher." onRetry={() => refetch()} />
  }

  const publisher = data.publisher
  const apps = data.apps ?? []

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      {/* Publisher header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{publisher.name}</h1>
          <p className="text-sm text-muted-foreground">
            {String(publisher.platform).toLowerCase() === 'ios' ? 'iOS' : 'Android'} Publisher
          </p>
        </div>
        {publisher.url && (
          <a href={publisher.url} target="_blank" rel="noopener noreferrer">
            <Button variant="outline" size="sm">
              <ExternalLink className="mr-1 h-4 w-4" />
              Store Page
            </Button>
          </a>
        )}
      </div>

      {/* Tracked apps — using AppCard */}
      {apps.length > 0 && (
        <div className="space-y-3">
          <h2 className="text-sm font-medium text-muted-foreground">
            Tracked Apps ({apps.length})
          </h2>
          <div className="grid gap-4 md:grid-cols-2">
            {apps.map((app) => (
              // eslint-disable-next-line @typescript-eslint/no-explicit-any
              <AppCard key={app.id} app={app as any} />
            ))}
          </div>
        </div>
      )}

      {/* Store apps */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-medium text-muted-foreground">
            {loadingStore ? 'Loading store apps...' : `All Apps in Store (${storeApps.length})`}
          </h2>
          {untrackedCount > 0 && (
            <Button variant="outline" size="sm" onClick={trackAll} disabled={importingAll}>
              {importingAll ? (
                <Loader2 className="mr-1 h-4 w-4 animate-spin" />
              ) : (
                <Plus className="mr-1 h-4 w-4" />
              )}
              Track All ({untrackedCount})
            </Button>
          )}
        </div>

        {loadingStore ? (
          <div className="grid gap-4 md:grid-cols-2">
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
            <Skeleton className="h-28 w-full" />
          </div>
        ) : storeApps.length > 0 ? (
          <div className="grid gap-4 md:grid-cols-2">
            {storeApps.map((app) => (
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
                    <p className="truncate text-sm font-medium">{app.name}</p>
                    <div className="ml-auto shrink-0">
                      <Button
                        variant={app.is_tracked ? 'outline' : 'default'}
                        size="sm"
                        className="h-7 text-xs"
                        onClick={(e: React.MouseEvent) => { e.preventDefault(); e.stopPropagation(); toggleApp(app.external_id, !!app.is_tracked) }}
                        disabled={importing.has(app.external_id)}
                      >
                        {importing.has(app.external_id) ? (
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
                  <div className="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground">
                    {app.category && <span>{app.category}</span>}
                    {app.category && <span>·</span>}
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
                    {!app.is_free && (
                      <Badge variant="outline" className="text-[10px]">Paid</Badge>
                    )}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        ) : (
          !loadingStore && publisher.external_id && (
            <p className="text-sm text-muted-foreground">No apps found in store.</p>
          )
        )}
      </div>
    </div>
  )
}
