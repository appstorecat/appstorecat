import { Check, Loader2, Fingerprint, FileText, BarChart3, Sparkles } from 'lucide-react'
import type { SyncStatus } from '@/hooks/useSyncStatus'

type Step = 'identity' | 'listings' | 'metrics' | 'finalize'

const STEPS: { key: Step; label: string; description: string; icon: typeof Fingerprint }[] = [
  { key: 'identity', label: 'Identity', description: 'Fetching app metadata from the store', icon: Fingerprint },
  { key: 'listings', label: 'Listings', description: 'Capturing localized store listings', icon: FileText },
  { key: 'metrics', label: 'Metrics', description: 'Collecting ratings and country availability', icon: BarChart3 },
  { key: 'finalize', label: 'Finalize', description: 'Computing diffs and publishing results', icon: Sparkles },
]

function stepState(current: string | null | undefined, step: Step): 'done' | 'active' | 'pending' {
  if (!current) return 'pending'
  const currentIdx = STEPS.findIndex((s) => s.key === current)
  const stepIdx = STEPS.findIndex((s) => s.key === step)
  if (currentIdx < 0 || stepIdx < 0) return 'pending'
  if (stepIdx < currentIdx) return 'done'
  if (stepIdx === currentIdx) return 'active'
  return 'pending'
}

export default function SyncingOverlay({ status }: { status: SyncStatus }) {
  const isQueued = status.status === 'queued' || !status.current_step
  const { done, total } = status.progress
  const pct = total > 0 ? Math.round((done / total) * 100) : 0

  return (
    <div className="mx-auto w-full max-w-2xl py-12">
      {/* Header */}
      <div className="mb-8 flex items-start gap-4 rounded-xl border bg-card p-5">
        <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
          <Loader2 className="h-6 w-6 animate-spin" />
        </div>
        <div className="flex-1">
          <h3 className="text-base font-semibold">
            {isQueued ? 'Waiting in queue' : 'Syncing data'}
          </h3>
          <p className="mt-0.5 text-sm text-muted-foreground">
            {isQueued
              ? 'We are about to start pulling fresh data. This can take up to a few minutes for new apps.'
              : 'Fetching the latest store data across locales and countries.'}
          </p>
          {total > 0 && (
            <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
              <span className="inline-flex items-center gap-1 font-medium text-foreground">
                {done} / {total} items ({pct}%)
              </span>
            </div>
          )}
        </div>
      </div>

      {/* Progress bar */}
      {total > 0 && (
        <div className="mb-6 h-1.5 w-full overflow-hidden rounded-full bg-muted">
          <div
            className="h-full bg-primary transition-all duration-300"
            style={{ width: `${pct}%` }}
          />
        </div>
      )}

      {/* Pipeline steps */}
      <div className="space-y-2">
        {STEPS.map((s, i) => {
          const state = stepState(status.current_step, s.key)
          const Icon = s.icon
          return (
            <div
              key={s.key}
              className={`flex items-center gap-3 rounded-lg border px-4 py-3 transition-colors ${
                state === 'active'
                  ? 'border-primary/40 bg-primary/5'
                  : state === 'done'
                    ? 'border-border bg-muted/30'
                    : 'border-dashed border-muted bg-transparent'
              }`}
            >
              <div
                className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                  state === 'done'
                    ? 'bg-primary text-primary-foreground'
                    : state === 'active'
                      ? 'bg-primary/15 text-primary'
                      : 'bg-muted text-muted-foreground'
                }`}
              >
                {state === 'done' ? (
                  <Check className="h-4 w-4" />
                ) : state === 'active' ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <Icon className="h-4 w-4" />
                )}
              </div>
              <div className="flex-1">
                <div className="flex items-center gap-2">
                  <p
                    className={`text-sm font-medium ${
                      state === 'pending' ? 'text-muted-foreground' : 'text-foreground'
                    }`}
                  >
                    {i + 1}. {s.label}
                  </p>
                  {state === 'active' && total > 0 && (
                    <span className="rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                      {pct}%
                    </span>
                  )}
                </div>
                <p className="text-xs text-muted-foreground">{s.description}</p>
              </div>
            </div>
          )
        })}
      </div>

      {/* Footnote */}
      <p className="mt-6 text-center text-xs text-muted-foreground">
        You can safely leave this page — sync will continue in the background.
      </p>
    </div>
  )
}
