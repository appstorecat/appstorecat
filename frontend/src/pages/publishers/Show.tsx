import { useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from '@/lib/axios'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { BookmarkMinus, BookmarkPlus, ExternalLink, Loader2, Plus, Smartphone, Star } from 'lucide-react'
import QueryError from '@/components/QueryError'
import AppCard from '@/components/AppCard'

interface StoreApp {
  external_id: string
  name: string
  icon_url: string | null
  rating: number | null
  rating_count: number | null
  is_free: boolean
  category: string | null
  is_tracked: boolean
}

function formatRatingCount(count: number | null): string {
  if (!count) return ''
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}

export default function PublishersShow() {
  const { platform, externalId } = useParams()
  const [searchParams] = useSearchParams()
  const nameParam = searchParams.get('name')
  const queryClient = useQueryClient()
  const [importing, setImporting] = useState<Set<string>>(new Set())
  const [importingAll, setImportingAll] = useState(false)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { data, isLoading, isError, refetch } = useQuery<any>({
    queryKey: ['publisher', platform, externalId],
    queryFn: () => axios.get(`/publishers/${platform}/${externalId}`, {
      params: nameParam ? { name: nameParam } : {},
    }).then((r) => r.data),
  })

  const { data: storeData, isLoading: loadingStore } = useQuery<StoreApp[]>({
    queryKey: ['publisher-store-apps', platform, externalId],
    queryFn: () => axios.get(`/publishers/${platform}/${externalId}/store-apps`, {
      params: nameParam ? { name: nameParam } : {},
    }).then((r) => r.data),
    enabled: !!data?.publisher.external_id,
  })

  const invalidateAll = () => {
    queryClient.invalidateQueries({ queryKey: ['publisher', platform, externalId] })
    queryClient.invalidateQueries({ queryKey: ['publisher-store-apps', platform, externalId] })
    queryClient.invalidateQueries({ queryKey: ['apps'] })
  }

  const toggleApp = async (extId: string, isTracked: boolean) => {
    if (!data || !platform) return
    setImporting((prev) => new Set(prev).add(extId))
    try {
      if (isTracked) {
        await axios.delete(`/apps/${platform}/${extId}/track`)
      } else {
        await axios.post(`/apps/${platform}/${extId}/track`)
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

  const trackAll = async () => {
    if (!storeData || !platform) return
    const untracked = storeData.filter((a) => !a.is_tracked).map((a) => a.external_id)
    if (untracked.length === 0) return
    if (!confirm(`Track ${untracked.length} app${untracked.length > 1 ? 's' : ''}?`)) return

    setImportingAll(true)
    try {
      for (const extId of untracked) {
        await axios.post(`/apps/${platform}/${extId}/track`)
      }
      invalidateAll()
    } finally {
      setImportingAll(false)
    }
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (isError || !data) {
    return <QueryError message="Failed to load publisher." onRetry={() => refetch()} />
  }

  const { publisher, apps } = data
  const storeApps: StoreApp[] = storeData ?? []
  const untrackedCount = storeApps.filter((a) => !a.is_tracked).length

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4">
      {/* Publisher header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{publisher.name}</h1>
          <p className="text-sm text-muted-foreground">
            {publisher.platform === 'ios' ? 'iOS' : 'Android'} Publisher
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
            {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
            {apps.map((app: any) => (
              <AppCard key={app.id} app={app} />
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
          <div className="flex items-center justify-center py-12">
            <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
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
                        onClick={(e: React.MouseEvent) => { e.preventDefault(); e.stopPropagation(); toggleApp(app.external_id, app.is_tracked) }}
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
