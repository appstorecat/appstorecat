import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Card, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { ArrowUpRight, Smartphone } from 'lucide-react'
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

  const content = (
    <Card className="transition-colors hover:bg-accent/20">
      <CardHeader className="flex flex-row flex-wrap items-center gap-3 space-y-0 pb-3">
        {group.app.icon_url ? (
          <img
            src={group.app.icon_url}
            alt=""
            className="h-10 w-10 shrink-0 rounded-xl"
          />
        ) : (
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
            <Smartphone className="h-4 w-4 text-muted-foreground" />
          </div>
        )}

        <div className="min-w-0 flex-1">
          <CardTitle className="truncate text-base">
            {group.app.name ?? 'Unknown App'}
          </CardTitle>
          <p className="mt-0.5 text-xs text-muted-foreground">
            {totalChanges} change{totalChanges === 1 ? '' : 's'}
          </p>
        </div>

        {Platform && (
          <span className="shrink-0 text-muted-foreground">
            <Platform className="h-4 w-4" />
          </span>
        )}

        {group.version && (
          <Badge variant="outline" className="font-mono text-[10px]">
            {group.previousVersion
              ? `v${group.previousVersion} → v${group.version}`
              : `v${group.version}`}
          </Badge>
        )}

        {detailHref && (
          <ArrowUpRight className="h-4 w-4 shrink-0 text-muted-foreground" />
        )}
      </CardHeader>

      <CardFooter className="flex flex-wrap items-center gap-2 border-t-0 bg-transparent pt-0 text-xs text-muted-foreground">
        <span className="inline-flex h-5 items-center leading-none">
          {formatRelative(group.detectedAt)}
        </span>
        {fieldSummary.map((f) => (
          <Badge key={f.field} variant="secondary" className="text-[10px]">
            {f.count > 1 ? `${f.count} × ` : ''}
            {f.label}
          </Badge>
        ))}
      </CardFooter>
    </Card>
  )

  if (detailHref) {
    return (
      <Link to={detailHref} className="block">
        {content}
      </Link>
    )
  }

  return content
}
