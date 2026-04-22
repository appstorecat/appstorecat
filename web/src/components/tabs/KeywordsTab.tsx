import { useEffect, useMemo, useRef, useState, useCallback } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useInfiniteQuery, type InfiniteData } from '@tanstack/react-query'
import { useDebounce } from '@/hooks/use-debounce'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
} from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Search, ChevronsUpDown, ArrowUpDown, ArrowUp, ArrowDown, X, Plus } from 'lucide-react'
import {
  appKeywords,
  getAppKeywordsQueryKey,
  useCompareKeywords,
} from '@/api/endpoints/apps/apps'
import type { AppVersion } from '@/api/models/appVersion'
import type { AppKeywords200 } from '@/api/models/appKeywords200'
import type { KeywordDensityResource } from '@/api/models/keywordDensityResource'
import type { KeywordCompareResourceAppsItem } from '@/api/models/keywordCompareResourceAppsItem'
import type { KeywordCompareResourceKeywords } from '@/api/models/keywordCompareResourceKeywords'
import type { AppKeywordsNgram } from '@/api/models/appKeywordsNgram'
import type { AppKeywordsSort } from '@/api/models/appKeywordsSort'
import type { AppKeywordsOrder } from '@/api/models/appKeywordsOrder'
import type { CompareKeywordsNgram } from '@/api/models/compareKeywordsNgram'

// --- Types ---

interface KeywordsTabProps {
  platform: string
  externalId: string
  versions: AppVersion[]
  selectedLocale: string
  selectedVersion: string
  allApps: { id: number; name: string; icon_url?: string | null }[]
}

interface KeywordRow {
  id: number | string
  keyword: string
  count: number
  density: number
  [key: string]: unknown
}

type SortableColumn = 'keyword' | 'count' | 'density'

const PER_PAGE = 100

// --- Locale helpers ---

function getFlagUrl(countryCode: string): string {
  return `https://flagcdn.com/w40/${countryCode}.png`
}

function getCountryName(countryCode: string): string {
  try {
    const display = new Intl.DisplayNames(['en'], { type: 'region' })
    return display.of(countryCode.toUpperCase()) ?? countryCode
  } catch {
    return countryCode
  }
}

function resolveLocale(primary: string | null | undefined, locales: string[]): string {
  if (locales.length === 0) return 'us'
  if (!primary) return locales.find((l) => l === 'us') ?? locales[0]
  const code = primary.toLowerCase()
  return locales.find((l) => l === code) ?? locales.find((l) => l === 'us') ?? locales[0]
}

function getDensityColor(density: number): string {
  if (density >= 3) return 'text-green-600 dark:text-green-400'
  if (density >= 1.5) return 'text-yellow-600 dark:text-yellow-400'
  return 'text-muted-foreground'
}

// --- Main component ---

export default function KeywordsTab({ platform, externalId, versions, selectedLocale, selectedVersion, allApps }: KeywordsTabProps) {
  const latestVersionId = versions[0]?.id
  const selectedVersionId = selectedVersion === 'latest' ? (latestVersionId ?? null) : Number(selectedVersion)
  const [selectedNgram, setSelectedNgram] = useState<string>('1')
  const [compareAppIds, setCompareAppIds] = useState<number[]>([])
  const [compareVersionIds, setCompareVersionIds] = useState<Record<number, number>>({})

  // URL-driven table state: search, sort, order are owned by the server and
  // reflected in the URL so the view is deep-linkable and identical to what
  // MCP / mobile clients would produce. No client-side filter/sort/slice.
  const [searchParams, setSearchParams] = useSearchParams()
  const urlSearch = searchParams.get('kw_search') ?? ''
  const urlSort = (searchParams.get('kw_sort') as SortableColumn | null) ?? 'density'
  const urlOrder = (searchParams.get('kw_order') as AppKeywordsOrder | null) ?? 'desc'

  const [searchInput, setSearchInput] = useState(urlSearch)
  const debouncedSearch = useDebounce(searchInput)

  const setParam = useCallback((key: string, value: string | null) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value === null || value === '') {
        next.delete(key)
      } else {
        next.set(key, value)
      }
      return next
    }, { replace: true })
  }, [setSearchParams])

  useEffect(() => {
    setParam('kw_search', debouncedSearch.trim() || null)
  }, [debouncedSearch, setParam])

  // Platform type coercion at the tab boundary — backend enum is lowercase.
  const platformParam = platform as 'ios' | 'android'
  const ngramNumber = Number(selectedNgram)

  const keywordParams = useMemo(() => ({
    version_id: selectedVersionId ?? undefined,
    locale: selectedLocale,
    ngram: ngramNumber as AppKeywordsNgram,
    per_page: PER_PAGE,
    sort: urlSort as AppKeywordsSort,
    order: urlOrder,
    ...(debouncedSearch.trim() ? { search: debouncedSearch.trim() } : {}),
  }), [selectedVersionId, selectedLocale, ngramNumber, urlSort, urlOrder, debouncedSearch])

  const keywordsEnabled = !!selectedVersionId && !!selectedLocale

  const {
    data: keywordsData,
    isLoading,
    fetchNextPage,
    hasNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery<AppKeywords200, void, InfiniteData<AppKeywords200, number>, readonly unknown[], number>({
    queryKey: getAppKeywordsQueryKey(platformParam, externalId, keywordParams) as readonly unknown[],
    queryFn: ({ pageParam, signal }) =>
      appKeywords(platformParam, externalId, { ...keywordParams, page: pageParam }, undefined, signal),
    initialPageParam: 1,
    enabled: keywordsEnabled,
    getNextPageParam: (lastPage: AppKeywords200) => {
      const meta = lastPage.meta as { current_page?: number; last_page?: number } | undefined
      const current = meta?.current_page ?? 0
      const last = meta?.last_page ?? 0
      return current < last ? current + 1 : undefined
    },
  })

  const keywords: KeywordDensityResource[] = useMemo(
    () => keywordsData?.pages.flatMap((p) => p.data ?? []) ?? [],
    [keywordsData],
  )
  const totalKeywords = (keywordsData?.pages[0]?.meta as { total?: number } | undefined)?.total ?? 0

  const versionIdsParam = useMemo(() => {
    const map: Record<string, number> = {}
    compareAppIds.forEach((id) => {
      if (compareVersionIds[id]) map[String(id)] = compareVersionIds[id]
    })
    return Object.keys(map).length > 0 ? map : undefined
  }, [compareAppIds, compareVersionIds])

  const { data: compareData } = useCompareKeywords(
    platformParam,
    externalId,
    {
      app_ids: compareAppIds,
      version_ids: versionIdsParam,
      locale: selectedLocale,
      ngram: ngramNumber as CompareKeywordsNgram,
    },
    {
      query: {
        enabled: compareAppIds.length > 0 && !!selectedLocale,
      },
    },
  )

  const addCompareApp = (id: number) => {
    if (compareAppIds.length >= 5 || compareAppIds.includes(id)) return
    setCompareAppIds([...compareAppIds, id])
  }

  const removeCompareApp = (id: number) => {
    setCompareAppIds(compareAppIds.filter((a) => a !== id))
    setCompareVersionIds((prev) => { const next = { ...prev }; delete next[id]; return next })
  }

  const setCompareVersion = (appId: number, versionId: number) => {
    setCompareVersionIds((prev) => ({ ...prev, [appId]: versionId }))
  }

  const availableApps = allApps.filter((a) => !compareAppIds.includes(a.id))

  const toggleSort = useCallback((column: SortableColumn) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      const currentSort = next.get('kw_sort') ?? 'density'
      const currentOrder = next.get('kw_order') ?? 'desc'

      if (currentSort === column) {
        // Same column → flip direction.
        next.set('kw_order', currentOrder === 'desc' ? 'asc' : 'desc')
      } else {
        next.set('kw_sort', column)
        next.set('kw_order', 'desc')
      }
      return next
    }, { replace: true })
  }, [setSearchParams])

  if (versions.length === 0) {
    return (
      <div className="py-12 text-center">
        <Search className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No keyword data available. Build DNA first.</p>
      </div>
    )
  }

  return (
    <div className="space-y-4">
      {/* Toolbar */}
      <div className="-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:justify-between sm:px-0 sm:pb-0 [&>*]:shrink-0">
        <Tabs value={selectedNgram} onValueChange={setSelectedNgram}>
          <TabsList>
            <TabsTrigger value="1">1-gram</TabsTrigger>
            <TabsTrigger value="2">2-gram</TabsTrigger>
            <TabsTrigger value="3">3-gram</TabsTrigger>
          </TabsList>
        </Tabs>
      </div>

      {/* Compare app selector */}
      {allApps.length > 0 && (
        <div className="flex flex-wrap items-center gap-2">
          {compareAppIds.map((id) => {
            const app = allApps.find((a) => a.id === id)
            const compareApp = compareData?.apps?.find((a) => a.id === id)
            const appVersions = compareApp?.versions ?? []
            const selectedVerId = compareVersionIds[id]
            const displayVersion = appVersions.find((v) => v.id === selectedVerId) ?? appVersions[0]

            return (
              <span key={id} className="inline-flex items-center gap-1.5 rounded-md border border-primary/20 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary">
                {app?.icon_url && (
                  <img src={app.icon_url} alt="" className="h-4 w-4 rounded-sm object-cover" />
                )}
                {app?.name ?? `App #${id}`}
                {appVersions.length > 1 && (
                  <Select
                    value={selectedVerId ? String(selectedVerId) : String(appVersions[0]?.id ?? '')}
                    onValueChange={(v) => setCompareVersion(id, Number(v))}
                  >
                    <SelectTrigger className="h-5 w-auto gap-0.5 border-0 bg-transparent px-1 py-0 text-[10px] font-normal text-primary shadow-none">
                      v{displayVersion?.version ?? '?'}
                    </SelectTrigger>
                    <SelectContent>
                      {appVersions.map((v, i) => (
                        <SelectItem key={v.id} value={String(v.id)}>
                          {i === 0 ? `Latest (v${v.version})` : `v${v.version}`}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
                {appVersions.length === 1 && displayVersion && (
                  <span className="text-[10px] font-normal opacity-70">v{displayVersion.version}</span>
                )}
                <button onClick={() => removeCompareApp(id)} className="ml-0.5 rounded-sm p-0.5 transition-colors hover:bg-primary/20">
                  <X className="h-3 w-3" />
                </button>
              </span>
            )
          })}
          {compareAppIds.length < 5 && availableApps.length > 0 && (
            <Popover>
              <PopoverTrigger render={<Button variant="outline" size="sm" className="h-7 border-dashed text-xs" />}>
                <Plus className="mr-1 h-3 w-3" />
                Compare
              </PopoverTrigger>
              <PopoverContent align="start" className="w-[250px] p-1">
                <div className="max-h-[200px] overflow-y-auto">
                  {availableApps.map((app) => (
                    <button
                      key={app.id}
                      className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
                      onClick={() => addCompareApp(app.id)}
                    >
                      {app.icon_url && (
                        <img src={app.icon_url} alt="" className="h-5 w-5 rounded-sm object-cover" />
                      )}
                      <span className="truncate">{app.name}</span>
                    </button>
                  ))}
                </div>
              </PopoverContent>
            </Popover>
          )}
        </div>
      )}

      {/* Table */}
      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>
      ) : keywords.length === 0 ? (
        <div className="py-12 text-center">
          <p className="text-sm text-muted-foreground">No {selectedNgram}-gram keywords found for this version and locale.</p>
        </div>
      ) : (
        <KeywordTable
          keywords={keywords}
          compareApps={compareData?.apps ?? []}
          compareKeywords={compareData?.keywords ?? {}}
          compareAppIds={compareAppIds}
          total={totalKeywords}
          sort={urlSort}
          order={urlOrder}
          onToggleSort={toggleSort}
          searchInput={searchInput}
          onSearchChange={setSearchInput}
          hasNextPage={!!hasNextPage}
          isFetchingNextPage={isFetchingNextPage}
          onLoadMore={() => fetchNextPage()}
        />
      )}
    </div>
  )
}

// --- Locale selector with popover ---

function LocaleSelector({ locales, selected, onChange }: { locales: string[]; selected: string; onChange: (v: string) => void }) {
  return (
    <Popover>
      <PopoverTrigger render={<Button variant="outline" className="w-[200px] justify-between" />}>
        <span className="flex items-center gap-2 truncate">
          <img src={getFlagUrl(selected)} alt={selected} className="h-3.5 w-5 rounded-[2px] object-cover" />
          {getCountryName(selected)}
        </span>
        <ChevronsUpDown className="ml-2 h-3.5 w-3.5 shrink-0 opacity-50" />
      </PopoverTrigger>
      <PopoverContent align="end" className="w-[200px] p-1">
        <div className="max-h-[300px] overflow-y-auto">
          {locales.map((locale) => (
            <button
              key={locale}
              className={`flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent ${locale === selected ? 'bg-accent' : ''}`}
              onClick={() => onChange(locale)}
            >
              <img src={getFlagUrl(locale)} alt={locale} className="h-3.5 w-5 rounded-[2px] object-cover" />
              {getCountryName(locale)}
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  )
}

// --- Keyword data table ---

function KeywordTable({
  keywords,
  compareApps,
  compareKeywords,
  compareAppIds,
  total,
  sort,
  order,
  onToggleSort,
  searchInput,
  onSearchChange,
  hasNextPage,
  isFetchingNextPage,
  onLoadMore,
}: {
  keywords: KeywordDensityResource[]
  compareApps: KeywordCompareResourceAppsItem[]
  compareKeywords: KeywordCompareResourceKeywords
  compareAppIds: number[]
  total: number
  sort: SortableColumn
  order: AppKeywordsOrder
  onToggleSort: (column: SortableColumn) => void
  searchInput: string
  onSearchChange: (value: string) => void
  hasNextPage: boolean
  isFetchingNextPage: boolean
  onLoadMore: () => void
}) {
  const tableContainerRef = useRef<HTMLDivElement>(null)

  const getCompareData = useCallback(
    (keyword: string, appId: number): { count: number; density: number } | null => {
      const raw = compareKeywords[String(appId)]?.[keyword]
      if (!raw) return null
      return { count: raw.count ?? 0, density: raw.density ?? 0 }
    },
    [compareKeywords],
  )

  // Primary rows arrive pre-sorted + filtered + paginated from the server.
  // Compare-only keywords (present in a competitor but not in the primary
  // listing) are appended as a visual merge because they have no server-side
  // source; the backend dictates ordering for everything visible by default.
  const tableData: KeywordRow[] = useMemo(() => {
    const rows: KeywordRow[] = keywords.map((kw) => {
      const row: KeywordRow = { id: kw.keyword, keyword: kw.keyword, count: kw.count, density: kw.density }
      compareAppIds.forEach((id) => { row[`comp_${id}`] = getCompareData(kw.keyword, id) })
      return row
    })

    if (compareAppIds.length > 0) {
      const existingKeywords = new Set(keywords.map((k) => k.keyword))
      const exclusiveKeywords = new Set<string>()

      compareAppIds.forEach((id) => {
        const appKws = compareKeywords[String(id)]
        if (appKws) Object.keys(appKws).forEach((k) => { if (!existingKeywords.has(k)) exclusiveKeywords.add(k) })
      })

      exclusiveKeywords.forEach((keyword) => {
        const row: KeywordRow = { id: `ext_${keyword}`, keyword, count: 0, density: 0 }
        compareAppIds.forEach((id) => { row[`comp_${id}`] = getCompareData(keyword, id) })
        rows.push(row)
      })
    }

    return rows
  }, [keywords, compareAppIds, compareKeywords, getCompareData])

  const renderSortIcon = (column: SortableColumn) => {
    if (sort !== column) return <ArrowUpDown className="h-3 w-3 opacity-30" />
    return order === 'asc' ? <ArrowUp className="h-3 w-3" /> : <ArrowDown className="h-3 w-3" />
  }

  // Auto-fetch next page as the user scrolls near the bottom. The explicit
  // "Load more" button remains as the primary affordance.
  useEffect(() => {
    const container = tableContainerRef.current
    if (!container) return

    const handleScroll = () => {
      const { scrollTop, scrollHeight, clientHeight } = container
      if (scrollHeight - scrollTop - clientHeight < 200 && hasNextPage && !isFetchingNextPage) {
        onLoadMore()
      }
    }

    container.addEventListener('scroll', handleScroll)
    return () => container.removeEventListener('scroll', handleScroll)
  }, [hasNextPage, isFetchingNextPage, onLoadMore])

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <CardTitle className="text-sm font-medium">
            {total} keyword{total !== 1 ? 's' : ''}
          </CardTitle>
          <div className="relative w-full sm:w-[200px]">
            <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={searchInput}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder="Filter keywords..."
              className="h-8 pl-8 text-xs"
            />
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div ref={tableContainerRef} className="max-h-[600px] overflow-auto">
          <table className="w-full table-sticky-first text-sm">
            <thead className="sticky top-0 z-10 bg-card">
              <tr className="border-b text-xs text-muted-foreground">
                <th className="bg-card pb-2 font-medium">
                  <button className="flex items-center gap-1" onClick={() => onToggleSort('keyword')}>
                    Keyword {renderSortIcon('keyword')}
                  </button>
                </th>
                <th className="pb-2 font-medium">
                  <button className="ml-auto flex items-center gap-1" onClick={() => onToggleSort('density')}>
                    Density {renderSortIcon('density')}
                  </button>
                </th>
                {compareAppIds.map((id) => {
                  const app = compareApps.find((a) => a.id === id)
                  const name = app?.name ?? `App #${id}`
                  return (
                    <th key={id} className="pb-2 font-medium">
                      <span className="ml-auto flex max-w-[150px] items-center gap-1.5 truncate" title={name}>
                        {app?.icon_url && (
                          <img src={app.icon_url} alt="" className="h-4 w-4 shrink-0 rounded-sm object-cover" />
                        )}
                        <span className="truncate">{name}</span>
                      </span>
                    </th>
                  )
                })}
              </tr>
            </thead>
            <tbody>
              {tableData.map((row) => (
                <tr key={row.id} className="border-b border-border/50 last:border-0">
                  <td className="bg-card py-1.5 pr-3">
                    <span className="font-mono text-xs">{row.keyword}</span>
                  </td>
                  <td className="py-1.5">
                    <div className={`text-right text-xs font-medium ${getDensityColor(row.density)}`}>
                      {row.count > 0 && <span className="font-normal text-muted-foreground">({row.count})</span>}{' '}
                      {row.density > 0 ? `${row.density}%` : '—'}
                    </div>
                  </td>
                  {compareAppIds.map((id) => {
                    const cd = row[`comp_${id}`] as { count: number; density: number } | null
                    return (
                      <td key={id} className="py-1.5">
                        <div className={`text-right text-xs font-medium ${cd ? getDensityColor(cd.density) : 'text-muted-foreground/30'}`}>
                          {cd ? <><span className="font-normal text-muted-foreground">({cd.count})</span> {cd.density}%</> : '—'}
                        </div>
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
          {hasNextPage && (
            <div className="py-3 text-center text-xs">
              <Button
                variant="outline"
                size="sm"
                onClick={onLoadMore}
                disabled={isFetchingNextPage}
                className="h-7 text-xs"
              >
                {isFetchingNextPage ? 'Loading…' : `Load more (${keywords.length} of ${total})`}
              </Button>
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
