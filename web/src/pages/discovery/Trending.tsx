import { useCallback } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useGetCharts } from '@/api/endpoints/charts/charts'
import { useListStoreCategories } from '@/api/endpoints/store-categories/store-categories'
import type { GetChartsCollection, GetChartsPlatform, ListStoreCategoriesPlatform } from '@/api/models'
import { Badge } from '@/components/ui/badge'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import PlatformSwitcher from '@/components/PlatformSwitcher'
import CountrySelect from '@/components/CountrySelect'
import { ArrowUp, ArrowDown, Minus, Smartphone, Clock } from 'lucide-react'

function timeAgo(dateStr: string): string {
  const diff = Date.now() - new Date(dateStr).getTime()
  const hours = Math.floor(diff / 3600000)
  if (hours < 1) return 'just now'
  if (hours === 1) return '1 hour ago'
  if (hours < 24) return `${hours} hours ago`
  const days = Math.floor(hours / 24)
  if (days === 1) return 'yesterday'
  return `${days} days ago`
}

export default function Trending() {
  const [searchParams, setSearchParams] = useSearchParams()

  const platform = searchParams.get('platform') || 'ios'
  const collection = searchParams.get('collection') || 'top_free'
  const countryCode = searchParams.get('country_code') || 'us'
  const categoryId = searchParams.get('category_id') || ''

  const setParam = useCallback((key: string, value: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) {
        next.set(key, value)
      } else {
        next.delete(key)
      }
      return next
    }, { replace: true })
  }, [setSearchParams])

  const setPlatform = (v: string | null) => {
    if (!v) return
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('platform', v)
      next.delete('category_id')
      return next
    }, { replace: true })
  }
  const setCollection = (v: string | null) => v && setParam('collection', v)
  const setCategoryId = (v: string | null) => setParam('category_id', v === 'all' ? '' : (v || ''))

  const { data: categories } = useListStoreCategories({
    platform: platform as ListStoreCategoriesPlatform,
    type: 'app',
  })

  const { data: chart, isPending, isFetching } = useGetCharts({
    platform: platform as GetChartsPlatform,
    collection: collection as GetChartsCollection,
    country_code: countryCode,
    ...(categoryId ? { category_id: Number(categoryId) } : {}),
  })

  // Show skeleton on any refetch (initial + filter changes) because chart
  // data changes meaning entirely when country/collection/category changes —
  // keeping the previous rows on screen would be misleading.
  const showLoading = isPending || isFetching

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Trending</h1>
        <p className="text-sm text-muted-foreground">
          Top charts from App Store and Google Play
        </p>
        {chart?.meta?.updated_at && (
          <div className="flex items-center gap-3 text-xs text-muted-foreground/60">
            <span className="flex items-center gap-1">
              <Clock className="h-3 w-3" />
              Updated {timeAgo(chart.meta.updated_at)}
            </span>
          </div>
        )}
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <PlatformSwitcher value={platform} onChange={setPlatform} />

        <CountrySelect value={countryCode} onChange={(v) => setParam('country_code', v)} />

        <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
          {([
            ['top_free', 'Top Free'],
            ['top_paid', 'Top Paid'],
            ['top_grossing', 'Top Grossing'],
          ] as const).map(([value, label]) => (
            <button
              key={value}
              type="button"
              onClick={() => setCollection(value)}
              className={`inline-flex cursor-pointer items-center rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                collection === value
                  ? 'bg-accent text-accent-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              {label}
            </button>
          ))}
        </div>

        <Select value={categoryId || ''} onValueChange={setCategoryId}>
          <SelectTrigger className="w-[180px]">
            <SelectValue>
              {categories?.find((c) => String(c.id) === categoryId)?.name
                ?? categories?.find((c) => c.external_id === null)?.name
                ?? 'All'}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            {categories?.map((cat) => (
              <SelectItem key={cat.id} value={String(cat.id)}>
                {cat.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      {showLoading ? (
        <div className="space-y-2 rounded-lg border p-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
        </div>
      ) : chart?.data && chart.data.length > 0 ? (
        <div className="overflow-x-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50 text-left text-xs font-medium text-muted-foreground">
                <th className="w-16 px-4 py-3">RANK</th>
                <th className="w-12 px-2 py-3"></th>
                <th className="px-4 py-3">APP</th>
                <th className="hidden px-4 py-3 md:table-cell">CATEGORY</th>
                <th className="px-4 py-3 text-right">PRICE</th>
              </tr>
            </thead>
            <tbody>
              {chart.data.map((entry) => (
                <tr
                  key={entry.app_external_id}
                  className="border-b transition-colors last:border-0 hover:bg-muted/30"
                >
                  <td className="px-4 py-3">
                    <span className={`font-semibold ${entry.rank && entry.rank <= 3 ? 'text-sidebar-accent-foreground' : 'text-muted-foreground'}`}>
                      #{entry.rank}
                    </span>
                  </td>
                  <td className="px-2 py-3">
                    <RankChange change={entry.rank_change ?? null} />
                  </td>
                  <td className="px-4 py-3">
                    <Link
                      to={`/apps/${platform}/${entry.app_external_id}`}
                      className="flex items-center gap-3 hover:underline"
                    >
                      {entry.icon_url ? (
                        <img
                          src={entry.icon_url}
                          alt={entry.app_name}
                          className="h-10 w-10 shrink-0 rounded-xl"
                        />
                      ) : (
                        <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                          <Smartphone className="h-4 w-4 text-muted-foreground" />
                        </div>
                      )}
                      <div className="min-w-0">
                        <p className="truncate font-medium">{entry.app_name}</p>
                        <p className="truncate text-xs text-muted-foreground">
                          {entry.publisher?.name || '—'}
                        </p>
                      </div>
                    </Link>
                  </td>
                  <td className="hidden px-4 py-3 text-xs text-muted-foreground md:table-cell">
                    {entry.category_name || '—'}
                  </td>
                  <td className="px-4 py-3 text-right">
                    {!entry.price || entry.price === 0 ? (
                      <Badge variant="secondary" className="text-xs">Free</Badge>
                    ) : (
                      <span className="text-xs font-medium">{Number(entry.price).toFixed(2)} {entry.currency ?? ''}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <p className="text-muted-foreground">
            No chart data available. Charts are updated daily.
          </p>
        </div>
      )}
    </div>
  )
}

function RankChange({ change }: { change: number | null }) {
  if (change === null) {
    return <Badge variant="outline" className="text-[10px]">NEW</Badge>
  }
  if (change === 0) {
    return <Minus className="h-3 w-3 text-muted-foreground" />
  }
  if (change > 0) {
    return (
      <span className="flex items-center gap-0.5 text-xs text-green-500">
        <ArrowUp className="h-3 w-3" />
        {change}
      </span>
    )
  }
  return (
    <span className="flex items-center gap-0.5 text-xs text-red-500">
      <ArrowDown className="h-3 w-3" />
      {Math.abs(change)}
    </span>
  )
}
