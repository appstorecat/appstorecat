import { useCallback, useEffect, useRef, useState } from 'react'
import { useDebounce } from '@/hooks/use-debounce'
import { Link, useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query'
import {
  exploreScreenshots,
  getExploreScreenshotsQueryKey,
} from '@/api/endpoints/explorer/explorer'
import { useListStoreCategories } from '@/api/endpoints/store-categories/store-categories'
import type {
  ExploreScreenshots200,
  ExploreScreenshotsPlatform,
  ExplorerScreenshotResource,
  StoreCategoryResource,
} from '@/api/models'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import PlatformSwitcher from '@/components/PlatformSwitcher'
import FilterBar from '@/components/FilterBar'
import { Smartphone } from 'lucide-react'

export default function Screenshots() {
  const [searchParams, setSearchParams] = useSearchParams()
  const sentinelRef = useRef<HTMLDivElement>(null)

  const platform = (searchParams.get('platform') || 'ios') as ExploreScreenshotsPlatform
  const categoryId = searchParams.get('category_id') || ''
  const [search, setSearch] = useState(searchParams.get('search') || '')
  const debouncedSearch = useDebounce(search)

  const setParam = (key: string, value: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) {
        next.set(key, value)
      } else {
        next.delete(key)
      }
      return next
    }, { replace: true })
  }

  const setPlatform = (v: string | null) => {
    if (!v) return
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      next.set('platform', v)
      next.delete('category_id')
      return next
    }, { replace: true })
  }

  const { data: categories } = useListStoreCategories({ platform, type: 'app' })

  const screenshotParams = {
    platform,
    per_page: 5,
    ...(categoryId ? { category_id: Number(categoryId) } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  }

  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery<ExploreScreenshots200, unknown, InfiniteData<ExploreScreenshots200, number>, readonly unknown[], number>({
    queryKey: getExploreScreenshotsQueryKey(screenshotParams) as readonly unknown[],
    queryFn: ({ pageParam, signal }) =>
      exploreScreenshots({ ...screenshotParams, page: pageParam }, undefined, signal),
    initialPageParam: 1,
    getNextPageParam: (lastPage: ExploreScreenshots200) => {
      // `meta` is typed as `{ [key: string]: unknown }` upstream; narrow locally.
      const meta = lastPage.meta as { current_page?: number; last_page?: number } | undefined
      const current = meta?.current_page ?? 0
      const last = meta?.last_page ?? 0
      return current < last ? current + 1 : undefined
    },
  })

  const apps: ExplorerScreenshotResource[] = data?.pages.flatMap((p) => p.data ?? []) ?? []

  // Infinite scroll observer
  useEffect(() => {
    const el = sentinelRef.current
    if (!el) return

    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting && hasNextPage && !isFetchingNextPage) {
          fetchNextPage()
        }
      },
      { rootMargin: '400px' },
    )
    observer.observe(el)
    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage])

  return (
    <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Screenshots</h1>
        <p className="text-sm text-muted-foreground">
          Browse store screenshots across all apps
        </p>
      </div>

      {/* Filters */}
      <FilterBar>
        <FilterBar.Search
          value={search}
          onChange={setSearch}
          placeholder="Search apps..."
        />
        <FilterBar.Controls>
          <PlatformSwitcher value={platform} onChange={setPlatform} />

          <Select value={categoryId} onValueChange={(v: string | null) => v && setParam('category_id', v)}>
            <SelectTrigger className="w-full sm:w-[180px]">
              <SelectValue>
                {categoryId ? categories?.find((c: StoreCategoryResource) => String(c.id) === categoryId)?.name ?? 'All Categories' : 'All Categories'}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="">All Categories</SelectItem>
              {categories?.map((cat: StoreCategoryResource) => (
                <SelectItem key={cat.id} value={String(cat.id)}>
                  {cat.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>

        </FilterBar.Controls>
      </FilterBar>

      {/* Content */}
      {isLoading ? (
        <div className="space-y-6">
          {Array.from({ length: 3 }).map((_, i) => (
            <div key={i} className="space-y-3">
              <Skeleton className="h-6 w-48" />
              <div className="flex gap-3 overflow-hidden">
                {Array.from({ length: 5 }).map((__, j) => (
                  <Skeleton key={j} className="h-72 w-40 shrink-0 rounded-xl" />
                ))}
              </div>
            </div>
          ))}
        </div>
      ) : apps.length === 0 ? (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <p className="text-muted-foreground">No screenshots found.</p>
        </div>
      ) : (
        <div className="space-y-6">
          {apps.map((app) => (
            <ScreenshotRow key={app.app_id} app={app} />
          ))}

          {/* Infinite scroll sentinel */}
          <div ref={sentinelRef} className="h-1" />

          {isFetchingNextPage && (
            <div className="flex items-center justify-center py-6">
              <div className="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function LazyScreenshot({ src, alt }: { src: string; alt: string }) {
  const [loaded, setLoaded] = useState(false)
  const [inView, setInView] = useState(false)

  const setRef = useCallback((el: HTMLDivElement | null) => {
    if (el && !inView) {
      const observer = new IntersectionObserver(
        ([entry]) => {
          if (entry.isIntersecting) {
            setInView(true)
            observer.disconnect()
          }
        },
        { rootMargin: '300px' },
      )
      observer.observe(el)
    }
  }, [inView])

  return (
    <div ref={setRef} className="shrink-0" style={{ height: '400px' }}>
      <div className="relative h-full rounded-[2rem] border-[3px] border-foreground/15 p-1 shadow-lg">
        <div className="h-full overflow-hidden rounded-[1.6rem]">
          {inView && (
            <img
              src={src}
              alt={alt}
              className={`h-full w-auto transition-opacity duration-300 ${loaded ? 'opacity-100' : 'opacity-0'}`}
              onLoad={() => setLoaded(true)}
            />
          )}
          {(!inView || !loaded) && (
            <div className="h-full animate-pulse bg-muted/20" style={{ aspectRatio: '9/19.5' }} />
          )}
        </div>
      </div>
    </div>
  )
}

function ScreenshotRow({ app }: { app: ExplorerScreenshotResource }) {
  return (
    <div className="space-y-3">
      {/* App header */}
      <Link
        to={`/apps/${app.platform}/${app.external_id}`}
        className="flex items-center gap-3 transition-opacity hover:opacity-80"
      >
        {app.icon_url ? (
          <img src={app.icon_url} alt="" className="h-10 w-10 rounded-xl" />
        ) : (
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-muted">
            <Smartphone className="h-4 w-4 text-muted-foreground" />
          </div>
        )}
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold">{app.name}</p>
          <p className="truncate text-xs text-muted-foreground">{app.publisher_name || '—'}</p>
        </div>
      </Link>

      {/* Screenshots carousel */}
      <div>
        <div
          className="flex gap-1.5 overflow-x-auto [-webkit-overflow-scrolling:touch] [overscroll-behavior-x:contain]"
          style={{ scrollbarWidth: 'none' }}
        >
          {app.screenshots?.map((ss, i) => (
            <LazyScreenshot
              key={i}
              src={ss.url}
              alt={`${app.name} screenshot ${i + 1}`}
            />
          ))}
        </div>
      </div>
    </div>
  )
}
