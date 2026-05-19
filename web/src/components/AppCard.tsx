import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { useTrackApp, useUntrackApp, useMoveAppToFolder } from '@/api/endpoints/apps/apps'
import { getListFoldersQueryKey } from '@/api/endpoints/folders/folders'
import type { AppResource, FolderResource } from '@/api/models'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import {
  Star,
  BookmarkPlus,
  BookmarkMinus,
  Loader2,
  MoreVertical,
  CircleSlash,
  Plus,
} from 'lucide-react'
import { folderDotClass } from '@/lib/folderColors'
import { cn } from '@/lib/utils'

interface AppCardProps {
  app: AppResource
  folders?: FolderResource[]
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

export default function AppCard({ app, folders }: AppCardProps) {
  const queryClient = useQueryClient()
  const trackMutation = useTrackApp()
  const untrackMutation = useUntrackApp()
  const moveMutation = useMoveAppToFolder()
  const tracking = trackMutation.isPending || untrackMutation.isPending
  const publisherName = app.publisher?.name ?? '—'
  const isTracked = app.is_tracked ?? false
  const [isDragging, setIsDragging] = useState(false)

  const move = (folderId: number | null) => {
    const platform = app.platform as 'ios' | 'android'
    const externalId = app.external_id
    moveMutation.mutate(
      { platform, externalId, data: { folder_id: folderId } },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: getListFoldersQueryKey() })
          queryClient.invalidateQueries({ queryKey: ['/apps'] })
        },
      },
    )
  }

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
      draggable
      onDragStart={(e) => {
        e.dataTransfer.setData('app/external-id', app.external_id)
        e.dataTransfer.setData('app/platform', app.platform)
        e.dataTransfer.effectAllowed = 'move'
        document.body.classList.add('dragging-app')
        setIsDragging(true)
      }}
      onDragEnd={() => {
        document.body.classList.remove('dragging-app')
        setIsDragging(false)
      }}
      className={cn(
        'flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm',
        isDragging && 'opacity-50',
      )}
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
              className="h-7 px-2 text-xs"
              onClick={toggleTrack}
              disabled={tracking}
              aria-label={isTracked ? 'Untrack app' : 'Track app'}
            >
              {tracking ? (
                <Loader2 className="h-3 w-3 animate-spin" />
              ) : isTracked ? (
                <>
                  <BookmarkMinus className="h-3 w-3 sm:mr-1" />
                  <span className="hidden sm:inline">Untrack</span>
                </>
              ) : (
                <>
                  <BookmarkPlus className="h-3 w-3 sm:mr-1" />
                  <span className="hidden sm:inline">Track</span>
                </>
              )}
            </Button>
            {folders !== undefined && isTracked && (
              <DropdownMenu>
                <DropdownMenuTrigger
                  render={
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-7 w-7 px-0"
                      aria-label="Move to folder"
                      onClick={(e) => {
                        e.preventDefault()
                        e.stopPropagation()
                      }}
                    />
                  }
                >
                  <MoreVertical className="h-3.5 w-3.5" />
                </DropdownMenuTrigger>
                <DropdownMenuContent
                  align="end"
                  onClick={(e) => {
                    e.preventDefault()
                    e.stopPropagation()
                  }}
                >
                  <DropdownMenuGroup>
                    <DropdownMenuLabel>Move to folder</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={() => move(null)}>
                      <CircleSlash className="h-3.5 w-3.5 text-muted-foreground" />
                      Unassigned
                    </DropdownMenuItem>
                    {folders.map((f) => (
                      <DropdownMenuItem key={f.id} onClick={() => move(f.id)}>
                        <span
                          className={cn('h-2.5 w-2.5 rounded-full', folderDotClass(f.color))}
                        />
                        {f.name}
                      </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    {/* TODO: wire up folder creation via a parent-managed FolderDialog */}
                    <DropdownMenuItem disabled>
                      <Plus className="h-3.5 w-3.5" />
                      New folder
                    </DropdownMenuItem>
                  </DropdownMenuGroup>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
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
