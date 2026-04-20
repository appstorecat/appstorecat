import { Loader2 } from 'lucide-react'
import type { SyncStatus } from '@/hooks/useSyncStatus'

const STEP_LABEL: Record<string, string> = {
  identity: 'Fetching app identity',
  listings: 'Fetching localized listings',
  metrics: 'Fetching country metrics',
  finalize: 'Finalizing',
  reconciling: 'Recovering failed items',
}

export default function SyncingOverlay({ status }: { status: SyncStatus }) {
  const stepLabel = status.current_step ? STEP_LABEL[status.current_step] ?? status.current_step : 'Queued'
  const { done, total } = status.progress
  const pct = total > 0 ? Math.round((done / total) * 100) : 0

  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <Loader2 className="mb-4 h-10 w-10 animate-spin text-muted-foreground" />
      <h3 className="text-lg font-semibold">{stepLabel}</h3>
      {total > 0 && (
        <>
          <p className="mt-1 text-sm text-muted-foreground">
            {done} / {total} ({pct}%)
          </p>
          <div className="mt-3 h-1 w-64 overflow-hidden rounded-full bg-muted">
            <div
              className="h-full bg-primary transition-all"
              style={{ width: `${pct}%` }}
            />
          </div>
        </>
      )}
      {status.elapsed_ms !== null && (
        <p className="mt-3 text-xs text-muted-foreground">
          {Math.round(status.elapsed_ms / 1000)}s elapsed
        </p>
      )}
    </div>
  )
}
