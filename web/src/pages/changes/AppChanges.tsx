import { useAppChanges } from '@/api/endpoints/change-monitor/change-monitor'
import type { ChangeResource } from '@/api/models'
import ChangeCard from '@/components/ChangeCard'

// The backend paginates this endpoint; Orval mistypes the response because the
// `#[OA\Response]` advertises a bare array. Read the real paginator envelope.
type PaginatedChanges = { data?: ChangeResource[] }

export default function AppChanges() {
  const { data, isLoading } = useAppChanges({ per_page: 50 })
  const changes = (data as unknown as PaginatedChanges | undefined)?.data ?? []

  return (
    <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">App Changes</h1>
        <p className="text-sm text-muted-foreground">Store listing changes across your tracked apps</p>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : changes.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 py-20 text-center">
          <p className="text-lg font-medium">No changes detected yet</p>
          <p className="text-sm text-muted-foreground">Changes will appear here when tracked apps update their store listings</p>
        </div>
      ) : (
        <div className="space-y-3">
          {changes.map((change) => (
            <ChangeCard
              key={change.id}
              appName={change.app?.name}
              appPlatform={change.app?.platform}
              appExternalId={change.app?.external_id}
              iconUrl={change.app?.icon_url}
              beforeVersion={change.previous_version}
              afterVersion={change.version}
              locale={change.locale ?? ''}
              fieldChanged={change.field_changed ?? ''}
              oldValue={change.old_value ?? null}
              newValue={change.new_value ?? null}
              detectedAt={change.detected_at ?? ''}
            />
          ))}
        </div>
      )}
    </div>
  )
}
