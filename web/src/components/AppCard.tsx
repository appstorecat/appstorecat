import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useTrackApp, useUntrackApp } from '@/api/endpoints/apps/apps'
import type { AppResource } from '@/api/models'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import { Star, BookmarkPlus, BookmarkMinus, Loader2 } from 'lucide-react'

interface AppCardProps {
  app: AppResource
}

// Invalidate everything that could have been filtered by "is_tracked" after a
// track toggle. Prefix-based invalidation covers every variant param tuple
// (tracked list, all-competitors groupings, search suggestions, detail view).
const TRACK_DEPENDENT_KEYS = [
  ['/apps'],
  ['/competitors'],
  ['/apps/search'],
  ['/publishers'],
] as const

function formatRatingCount(count: number | null): string {
  if (!count) return ''
  if (count >= 1_000_000) return `${(count / 1_000_000).toFixed(1)}M`
  if (count >= 1_000) return `${(count / 1_000).toFixed(1)}K`
  return count.toString()
}

const IosSvg = () => <AppStoreSvg className="h-4 w-4 shrink-0 text-muted-foreground" />
const AndroidSvg = () => <GooglePlaySvg className="h-4 w-4 shrink-0 text-muted-foreground" />

export default function AppCard({ app }: AppCardProps) {
  const queryClient = useQueryClient()
  const trackMutation = useTrackApp()
  const untrackMutation = useUntrackApp()
  const tracking = trackMutation.isPending || untrackMutation.isPending
  const publisherName = app.publisher?.name ?? '—'
  const isTracked = app.is_tracked ?? false

  const toggleTrack = async (e: React.MouseEvent) => {
    e.preventDefault()
    e.stopPropagation()
    const platform = app.platform as 'ios' | 'android'
    const externalId = app.external_id
    try {
      if (isTracked) {
        await untrackMutation.mutateAsync({ platform, externalId })
      } else {
        await trackMutation.mutateAsync({ platform, externalId })
      }
      for (const queryKey of TRACK_DEPENDENT_KEYS) {
        await queryClient.invalidateQueries({ queryKey: [...queryKey] })
      }
    } catch {
      // network errors surface via the mutation state if needed
    }
  }

  return (
    <Link
      to={`/apps/${app.platform}/${app.external_id}`}
      className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm"
    >
      {app.icon_url ? (
        <img src={app.icon_url} alt={app.name} className="h-14 w-14 shrink-0 rounded-xl" />
      ) : (
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-muted">
          {app.platform === 'ios' ? <IosSvg /> : <AndroidSvg />}
        </div>
      )}

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <p className="truncate text-sm font-medium">{app.name}</p>
          {app.is_available === false && (
            <Badge variant="destructive" className="shrink-0 text-[9px]">Removed</Badge>
          )}
          <div className="ml-auto flex shrink-0 items-center gap-1.5">
            {app.platform === 'ios' ? <IosSvg /> : <AndroidSvg />}
            <Button
              variant={isTracked ? 'outline' : 'default'}
              size="sm"
              className="h-7 text-xs"
              onClick={toggleTrack}
              disabled={tracking}
            >
              {tracking ? (
                <Loader2 className="h-3 w-3 animate-spin" />
              ) : isTracked ? (
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
        <p className="truncate text-xs text-muted-foreground" title={publisherName}>
          {publisherName.length > 30 ? publisherName.slice(0, 30) + '...' : publisherName}
        </p>
        <div className="mt-1.5 flex items-center gap-2 text-xs text-muted-foreground">
          {app.category && <span>{app.category.name}</span>}
          {app.category && <span>·</span>}
          {app.rating && Number(app.rating) > 0 ? (
            <span className="flex items-center gap-0.5">
              <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
              {Number(app.rating).toFixed(1)}
              {app.rating_count && (
                <span className="text-muted-foreground/50">({formatRatingCount(app.rating_count)})</span>
              )}
            </span>
          ) : (
            <span className="text-muted-foreground/50">No Ratings</span>
          )}
        </div>
      </div>
    </Link>
  )
}
