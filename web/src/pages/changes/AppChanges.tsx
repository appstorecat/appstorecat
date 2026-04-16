import { useQuery } from '@tanstack/react-query'
import axios from '@/lib/axios'
import ChangeCard from '@/components/ChangeCard'

interface Change {
  id: number
  app: {
    id: number
    name: string
    platform: string
    external_id: string
    icon_url: string | null
  } | null
  version: string | null
  previous_version: string | null
  language: string
  field_changed: string
  old_value: string | null
  new_value: string | null
  detected_at: string
}

export default function AppChanges() {
  const { data, isLoading } = useQuery<{ data: Change[] }>({
    queryKey: ['changes', 'apps'],
    queryFn: () => axios.get('/changes/apps?per_page=50').then((r) => r.data),
  })

  const changes = data?.data ?? []

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
              language={change.language}
              fieldChanged={change.field_changed}
              oldValue={change.old_value}
              newValue={change.new_value}
              detectedAt={change.detected_at}
            />
          ))}
        </div>
      )}
    </div>
  )
}
