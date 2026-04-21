import { useEffect, useRef, useState } from 'react'
import { useDebounce } from '@/hooks/use-debounce'
import { Link, useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query'
import {
  exploreIcons,
  getExploreIconsQueryKey,
} from '@/api/endpoints/explorer/explorer'
import { useListStoreCategories } from '@/api/endpoints/store-categories/store-categories'
import type {
  ExploreIcons200,
  ExploreIconsPlatform,
  ExplorerIconResource,
  StoreCategoryResource,
} from '@/api/models'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import PlatformSwitcher from '@/components/PlatformSwitcher'
import { Search, Smartphone, ExternalLink } from 'lucide-react'

export default function Icons() {
  const [searchParams, setSearchParams] = useSearchParams()
  const sentinelRef = useRef<HTMLDivElement>(null)

  const platform = (searchParams.get('platform') || 'ios') as ExploreIconsPlatform
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

  const iconParams = {
    platform,
    per_page: 100,
    ...(categoryId ? { category_id: Number(categoryId) } : {}),
    ...(debouncedSearch ? { search: debouncedSearch } : {}),
  }

  const {
    data,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery<ExploreIcons200, unknown, InfiniteData<ExploreIcons200, number>, readonly unknown[], number>({
    queryKey: getExploreIconsQueryKey(iconParams) as readonly unknown[],
    queryFn: ({ pageParam, signal }) =>
      exploreIcons({ ...iconParams, page: pageParam }, undefined, signal),
    initialPageParam: 1,
    getNextPageParam: (lastPage: ExploreIcons200) => {
      // `meta` is typed as `{ [key: string]: unknown }` upstream; narrow locally.
      const meta = lastPage.meta as { current_page?: number; last_page?: number } | undefined
      const current = meta?.current_page ?? 0
      const last = meta?.last_page ?? 0
      return current < last ? current + 1 : undefined
    },
  })

  const icons: ExplorerIconResource[] = data?.pages.flatMap((p) => p.data ?? []) ?? []
  const total = (data?.pages[0]?.meta as { total?: number } | undefined)?.total ?? 0

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
        <h1 className="text-2xl font-bold tracking-tight">App Icons</h1>
        <p className="text-sm text-muted-foreground">
          Explore icon design trends across app stores
        </p>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search apps..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-[200px] pl-9"
          />
        </div>
        <PlatformSwitcher value={platform} onChange={setPlatform} />

        <Select value={categoryId} onValueChange={(v: string | null) => v && setParam('category_id', v)}>
          <SelectTrigger className="w-[180px]">
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

        {total > 0 && (
          <span className="text-xs text-muted-foreground">
            {total} app{total !== 1 ? 's' : ''}
          </span>
        )}
      </div>

      {/* Grid */}
      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : icons.length === 0 ? (
        <div className="rounded-lg border border-dashed p-12 text-center">
          <p className="text-muted-foreground">No icons found.</p>
        </div>
      ) : (
        <div className="grid grid-cols-3 gap-3 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10">
          {icons.map((app) => (
            <IconCard key={app.app_id} app={app} />
          ))}

          <div ref={sentinelRef} className="h-1" />

          {isFetchingNextPage && (
            <div className="col-span-full flex items-center justify-center py-6">
              <div className="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
            </div>
          )}
        </div>
      )}
    </div>
  )
}

function IconCard({ app }: { app: ExplorerIconResource }) {
  const [hovered, setHovered] = useState(false)
  const [popoverSide, setPopoverSide] = useState<'center' | 'left' | 'right'>('center')
  const cardRef = useRef<HTMLAnchorElement>(null)

  const handleMouseEnter = () => {
    if (cardRef.current) {
      const rect = cardRef.current.getBoundingClientRect()
      if (rect.left < 400) {
        setPopoverSide('right')
      } else if (window.innerWidth - rect.right < 280) {
        setPopoverSide('left')
      } else {
        setPopoverSide('center')
      }
    }
    setHovered(true)
  }

  const popoverPosition =
    popoverSide === 'right' ? 'left-0'
    : popoverSide === 'left' ? 'right-0'
    : 'left-1/2 -translate-x-1/2'

  const arrowPosition =
    popoverSide === 'right' ? 'left-6'
    : popoverSide === 'left' ? 'right-6'
    : 'left-1/2 -translate-x-1/2'

  return (
    <Link
      ref={cardRef}
      to={`/apps/${app.platform}/${app.external_id}`}
      className="group relative flex flex-col items-center gap-2 rounded-xl p-2 transition-all duration-200"
      onMouseEnter={handleMouseEnter}
      onMouseLeave={() => setHovered(false)}
    >
      <div className="relative">
        {app.icon_url ? (
          <img
            src={app.icon_url}
            alt={app.name}
            className="w-24 aspect-square rounded-[22px] shadow-md transition-all duration-300 group-hover:scale-110 group-hover:shadow-[0_0_25px_rgba(255,255,255,0.15)]"
            loading="lazy"
          />
        ) : (
          <div className="flex h-16 w-16 items-center justify-center rounded-[18px] bg-muted shadow-md">
            <Smartphone className="h-6 w-6 text-muted-foreground" />
          </div>
        )}
      </div>
      {/* Hover tooltip */}
      {hovered && (
        <div className={`absolute -top-28 z-[999] w-64 rounded-xl border bg-popover p-3.5 shadow-xl ${popoverPosition}`}>
          <div className="flex items-center gap-3">
            {app.icon_url && (
              <img src={app.icon_url} alt="" className="h-12 w-12 shrink-0 rounded-xl" />
            )}
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold">{app.name}</p>
              <p className="truncate text-xs text-muted-foreground">{app.publisher_name || '—'}</p>
            </div>
          </div>
          {app.category_name && (
            <div className="mt-2 flex items-center justify-between">
              <span className="text-xs text-muted-foreground">{app.category_name}</span>
              <ExternalLink className="h-3.5 w-3.5 text-muted-foreground/50" />
            </div>
          )}
          <div className={`absolute -bottom-1 h-2 w-2 rotate-45 border-b border-r bg-popover ${arrowPosition}`} />
        </div>
      )}
    </Link>
  )
}
