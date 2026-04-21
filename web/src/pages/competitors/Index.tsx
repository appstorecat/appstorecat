import { Link } from 'react-router-dom'
import { useListAllCompetitors } from '@/api/endpoints/apps/apps'
import type { CompetitorGroupResource } from '@/api/models'
import AppCard from '@/components/AppCard'
import QueryError from '@/components/QueryError'
import { Skeleton } from '@/components/ui/skeleton'
import { Badge } from '@/components/ui/badge'
import { Users } from 'lucide-react'

export default function CompetitorsIndex() {
  const { data, isLoading, isError, refetch } = useListAllCompetitors()

  if (isLoading) {
    return (
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <Skeleton className="h-16 w-64" />
        <Skeleton className="h-40 w-full" />
        <Skeleton className="h-40 w-full" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load competitors." onRetry={() => refetch()} />
  }

  const groups = data ?? []
  const totalCompetitors = groups.reduce((sum, g) => sum + g.competitors.length, 0)

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Competitors</h1>
        <p className="text-sm text-muted-foreground">
          {totalCompetitors} competitor{totalCompetitors === 1 ? '' : 's'} across{' '}
          {groups.length} tracked app{groups.length === 1 ? '' : 's'}
        </p>
      </div>

      {groups.length === 0 ? (
        <div className="rounded-xl border border-dashed p-12 text-center">
          <Users className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            No competitors yet. Add competitors from an app's Competitors tab.
          </p>
        </div>
      ) : (
        <div className="space-y-8">
          {groups.map((group) => (
            <ParentGroup key={group.parent.id} group={group} />
          ))}
        </div>
      )}
    </div>
  )
}

function ParentGroup({ group }: { group: CompetitorGroupResource }) {
  const { parent, competitors } = group

  return (
    <section className="space-y-3">
      <Link
        to={`/apps/${parent.platform}/${parent.external_id}`}
        className="group flex items-center gap-3 rounded-lg px-1 py-1 transition-colors hover:bg-accent/40"
      >
        {parent.icon_url ? (
          <img
            src={parent.icon_url}
            alt={parent.name}
            className="h-9 w-9 shrink-0 rounded-lg"
          />
        ) : (
          <div className="h-9 w-9 shrink-0 rounded-lg bg-muted" />
        )}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <h2 className="truncate text-base font-semibold group-hover:underline">
              {parent.name}
            </h2>
            <Badge variant="secondary" className="shrink-0 text-[10px]">
              {competitors.length} competitor{competitors.length === 1 ? '' : 's'}
            </Badge>
          </div>
          <p className="truncate text-xs text-muted-foreground">
            {parent.publisher?.name ?? '—'}
          </p>
        </div>
      </Link>

      <div className="ml-5 space-y-2 border-l border-border pl-5">
        <div className="grid gap-3 md:grid-cols-2">
          {competitors.map((c) => (
            <AppCard key={c.id} app={c.app} />
          ))}
        </div>
      </div>
    </section>
  )
}
