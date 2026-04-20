import { useQuery, useQueryClient } from '@tanstack/react-query'
import axios from '@/lib/axios'
import { useEffect, useRef } from 'react'

export interface FailedItem {
  type: 'listing' | 'metric'
  country?: string
  language?: string
  reason?: string
  retry_count?: number
  permanent_failure?: boolean
  next_retry_at?: string | null
  last_error?: string | null
}

export interface SyncStatus {
  app_id: number
  status: 'queued' | 'processing' | 'completed' | 'failed'
  current_step: 'identity' | 'listings' | 'metrics' | 'finalize' | 'reconciling' | null
  progress: { done: number; total: number }
  failed_items: FailedItem[]
  failed_items_count: number
  error_message: string | null
  job_id: string | null
  started_at: string | null
  completed_at: string | null
  next_retry_at: string | null
  elapsed_ms: number | null
}

export function useSyncStatus(
  platform: string | undefined,
  externalId: string | undefined,
  opts: { enabled?: boolean; autoTrigger?: boolean } = {},
) {
  const enabled = (opts.enabled ?? true) && !!platform && !!externalId
  const autoTrigger = opts.autoTrigger ?? true
  const queryClient = useQueryClient()
  const triggeredRef = useRef(false)

  const query = useQuery<SyncStatus>({
    queryKey: ['sync-status', platform, externalId],
    queryFn: async () => {
      const res = await axios.get(`/apps/${platform}/${externalId}/sync-status`)
      return res.data
    },
    enabled,
    refetchInterval: (q) => {
      const data = q.state.data
      if (!data) return 2000
      return data.status === 'queued' || data.status === 'processing' ? 2000 : false
    },
  })

  // Auto-dispatch a sync job if the app has never been synced and no active
  // sync is in flight. Runs once per mount.
  useEffect(() => {
    if (!autoTrigger || !platform || !externalId || !query.data) return
    const data = query.data
    const needsSync =
      !data.completed_at && data.status !== 'processing' && data.status !== 'queued'
    if (needsSync && !triggeredRef.current) {
      triggeredRef.current = true
      axios.post(`/apps/${platform}/${externalId}/sync`).then(() => {
        queryClient.invalidateQueries({ queryKey: ['sync-status', platform, externalId] })
      })
    }
  }, [autoTrigger, platform, externalId, query.data, queryClient])

  // When a sync transitions to completed, invalidate the app detail query so
  // the UI picks up freshly-synced listings/metrics.
  useEffect(() => {
    if (query.data?.status === 'completed' && query.data.progress.done > 0) {
      queryClient.invalidateQueries({ queryKey: ['apps', platform, externalId] })
    }
  }, [query.data?.status, query.data?.progress.done, queryClient, platform, externalId])

  return query
}

export async function triggerSync(platform: string, externalId: string): Promise<SyncStatus> {
  const res = await axios.post(`/apps/${platform}/${externalId}/sync`)
  return res.data
}
