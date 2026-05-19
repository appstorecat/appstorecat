import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Smartphone } from 'lucide-react'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import type { ChangeResource } from '@/api/models/changeResource'

export interface ChangeGroup {
  key: string
  app: NonNullable<ChangeResource['app']>
  previousVersion: string | null
  version: string | null
  detectedAt: string
  rows: ChangeResource[]
}

interface ChangeGroupCardProps {
  group: ChangeGroup
}

const FIELD_LABELS: Record<string, string> = {
  title: 'Title',
  subtitle: 'Subtitle',
  description: 'Description',
  whats_new: "What's New",
  screenshots: 'Screenshots',
  locale_added: 'Locale Added',
  locale_removed: 'Locale Removed',
}

function formatRelative(dateStr: string): string {
  const then = new Date(dateStr).getTime()
  if (Number.isNaN(then)) return dateStr
  const diff = Date.now() - then
  const minutes = Math.floor(diff / 60_000)
  if (minutes < 60) return minutes <= 1 ? 'just now' : `${minutes} min ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days === 1) return 'yesterday'
  if (days < 30) return `${days}d ago`
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function ChangeGroupCard({ group }: ChangeGroupCardProps) {
  const byField = new Map<string, ChangeResource[]>()
  for (const row of group.rows) {
    const f = row.field_changed ?? 'unknown'
    const list = byField.get(f) ?? []
    list.push(row)
    byField.set(f, list)
  }

  const fieldSummary = Array.from(byField.entries()).map(([field, rows]) => ({
    field,
    label: FIELD_LABELS[field] ?? field,
    count: rows.length,
  }))

  const detailHref =
    group.app.platform && group.app.external_id
      ? `/apps/${group.app.platform}/${group.app.external_id}?tab=changes`
      : null

  const Platform =
    group.app.platform === 'ios'
      ? AppStoreSvg
      : group.app.platform === 'android'
        ? GooglePlaySvg
        : null

  const totalChanges = group.rows.length

  const body = (
    <>
      {group.app.icon_url ? (
        <img
          src={group.app.icon_url}
          alt=""
          className="h-14 w-14 shrink-0 rounded-xl"
        />
      ) : (
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-muted">
          <Smartphone className="h-5 w-5 text-muted-foreground" />
        </div>
      )}

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <p className="truncate text-sm font-medium">
            {group.app.name ?? 'Unknown App'}
          </p>
          <div className="ml-auto flex shrink-0 items-center gap-1.5">
            {Platform && <Platform className="h-4 w-4 text-muted-foreground" />}
            {group.version && (
              <Badge variant="outline" className="font-mono text-[10px]">
                {group.previousVersion
                  ? `v${group.previousVersion} → v${group.version}`
                  : `v${group.version}`}
              </Badge>
            )}
          </div>
        </div>
        <p className="truncate text-xs text-muted-foreground">
          {totalChanges} change{totalChanges === 1 ? '' : 's'} · {formatRelative(group.detectedAt)}
        </p>
        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
          {fieldSummary.map((f) => (
            <Badge key={f.field} variant="secondary" className="text-[10px]">
              {f.count > 1 ? `${f.count} × ` : ''}
              {f.label}
            </Badge>
          ))}
        </div>
      </div>
    </>
  )

  const cardClass =
    'flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm'

  if (detailHref) {
    return (
      <Link to={detailHref} className={cardClass}>
        {body}
      </Link>
    )
  }

  return <div className={cardClass}>{body}</div>
}
