import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import {
  getAppSyncStatusQueryKey,
  getShowAppQueryKey,
  useAppSyncStatus,
  useSyncApp,
} from '@/api/endpoints/apps/apps'
import type {
  SyncStatusResource,
  SyncStatusResourceFailedItemsItem,
} from '@/api/models'

// Re-export the Orval resource under legacy names so existing consumers
// (PartialSyncBanner, SyncingOverlay) keep working without edits. The runtime
// contract asserts that `progress`, `failed_items`, `failed_items_count`, and
// `status` are always populated by the API — narrow the optional resource
// shape to that contract for component ergonomics.
export type FailedItem = SyncStatusResourceFailedItemsItem
export type SyncStatus = SyncStatusResource & {
  status: NonNullable<SyncStatusResource['status']>
  progress: NonNullable<SyncStatusResource['progress']> & {
    done: number
    total: number
  }
  failed_items: SyncStatusResourceFailedItemsItem[]
  failed_items_count: number
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

  const narrowPlatform = (platform ?? 'ios') as 'ios' | 'android'
  const narrowExternalId = externalId ?? ''

  const query = useAppSyncStatus<SyncStatus>(narrowPlatform, narrowExternalId, {
    query: {
      enabled,
      refetchInterval: (q) => {
        const data = q.state.data
        if (!data) return 2000
        return data.status === 'queued' || data.status === 'processing' ? 2000 : false
      },
    },
  })

  const syncMutation = useSyncApp()

  // Auto-dispatch a sync job if the app has never been synced and no active
  // sync is in flight. Runs once per mount.
  useEffect(() => {
    if (!autoTrigger || !platform || !externalId || !query.data) return
    const data = query.data
    const needsSync =
      !data.completed_at && data.status !== 'processing' && data.status !== 'queued'
    if (needsSync && !triggeredRef.current) {
      triggeredRef.current = true
      syncMutation.mutate(
        { platform: narrowPlatform, externalId: narrowExternalId },
        {
          onSuccess: () => {
            queryClient.invalidateQueries({
              queryKey: getAppSyncStatusQueryKey(narrowPlatform, narrowExternalId),
            })
          },
        },
      )
    }
  }, [autoTrigger, platform, externalId, query.data, queryClient, syncMutation, narrowPlatform, narrowExternalId])

  // When a sync transitions to completed, invalidate the app detail query so
  // the UI picks up freshly-synced listings/metrics.
  useEffect(() => {
    if (query.data?.status === 'completed' && (query.data.progress?.done ?? 0) > 0) {
      queryClient.invalidateQueries({
        queryKey: getShowAppQueryKey(narrowPlatform, narrowExternalId),
      })
    }
  }, [query.data?.status, query.data?.progress?.done, queryClient, narrowPlatform, narrowExternalId])

  return query
}
