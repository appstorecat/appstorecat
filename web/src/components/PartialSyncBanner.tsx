import { AlertTriangle } from 'lucide-react'
import type { SyncStatus } from '@/hooks/useSyncStatus'

export default function PartialSyncBanner({ status }: { status: SyncStatus }) {
  if (status.failed_items_count === 0) return null

  const permanent = status.failed_items.filter((i) => i.permanent_failure).length
  const retrying = status.failed_items_count - permanent

  return (
    <div className="flex items-start gap-3 rounded-lg border border-yellow-500/40 bg-yellow-500/10 px-4 py-3 text-sm">
      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-yellow-500" />
      <div className="flex-1">
        <p className="font-medium">Sync partially completed</p>
        <p className="mt-0.5 text-muted-foreground">
          {retrying > 0 && (
            <>
              {retrying} data point{retrying === 1 ? '' : 's'} not yet fetched — will retry automatically.
            </>
          )}
          {retrying > 0 && permanent > 0 && ' '}
          {permanent > 0 && (
            <>
              {permanent} item{permanent === 1 ? '' : 's'} unavailable (app not active in those regions).
            </>
          )}
        </p>
      </div>
    </div>
  )
}
