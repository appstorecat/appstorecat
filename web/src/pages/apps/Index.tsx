import { useQuery } from '@tanstack/react-query'
import axios from '@/lib/axios'
import { Smartphone } from 'lucide-react'
import AppCard from '@/components/AppCard'
import QueryError from '@/components/QueryError'

export default function AppsIndex() {
  const { data: apps, isLoading, isError, refetch } = useQuery({
    queryKey: ['apps'],
    queryFn: () => axios.get('/apps').then((r) => r.data),
  })

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (isError) {
    return <QueryError message="Failed to load apps." onRetry={() => refetch()} />
  }

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Apps</h1>
        <p className="text-sm text-muted-foreground">Your tracked apps ({apps?.length ?? 0})</p>
      </div>

      {apps && apps.length > 0 ? (
        <div className="grid gap-4 md:grid-cols-2">
          {apps.map((app: Record<string, unknown>) => (
            <AppCard key={app.id as number} app={app as never} />
          ))}
        </div>
      ) : (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <Smartphone className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
          <p className="text-muted-foreground">
            No apps tracked yet. Go to Discover to find and track apps.
          </p>
        </div>
      )}
    </div>
  )
}
