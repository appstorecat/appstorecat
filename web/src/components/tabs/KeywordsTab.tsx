import { useState, useMemo, useCallback, useRef, useEffect } from 'react'
import {
  useReactTable,
  getCoreRowModel,
  getSortedRowModel,
  getFilteredRowModel,
  type SortingState,
  type ColumnDef,
  flexRender,
} from '@tanstack/react-table'
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
import { useAppKeywords, useCompareKeywords } from '@/api/endpoints/apps/apps'
import type { AppVersion } from '@/api/models/appVersion'
import type { KeywordDensityResource } from '@/api/models/keywordDensityResource'
import type { KeywordCompareResourceAppsItem } from '@/api/models/keywordCompareResourceAppsItem'
import type { KeywordCompareResourceKeywords } from '@/api/models/keywordCompareResourceKeywords'
import type { AppKeywordsNgram } from '@/api/models/appKeywordsNgram'
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
  const sortedVersions = useMemo(() => [...versions].sort((a, b) => b.id - a.id), [versions])
  const latestVersionId = sortedVersions[0]?.id
  const selectedVersionId = selectedVersion === 'latest' ? (latestVersionId ?? null) : Number(selectedVersion)
  const [selectedNgram, setSelectedNgram] = useState<string>('1')
  const [compareAppIds, setCompareAppIds] = useState<number[]>([])
  const [compareVersionIds, setCompareVersionIds] = useState<Record<number, number>>({})

  // Platform type coercion at the tab boundary — backend enum is lowercase.
  const platformParam = platform as 'ios' | 'android'
  const ngramNumber = Number(selectedNgram)

  const { data: keywords, isLoading } = useAppKeywords(
    platformParam,
    externalId,
    {
      version_id: selectedVersionId ?? undefined,
      locale: selectedLocale,
      ngram: ngramNumber as AppKeywordsNgram,
    },
    {
      query: {
        enabled: !!selectedVersionId && !!selectedLocale,
      },
    },
  )

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
      <div className="flex flex-wrap items-center justify-between gap-3">
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
      ) : !keywords || keywords.length === 0 ? (
        <div className="py-12 text-center">
          <p className="text-sm text-muted-foreground">No {selectedNgram}-gram keywords found for this version and locale.</p>
        </div>
      ) : (
        <KeywordTable
          keywords={keywords}
          compareApps={compareData?.apps ?? []}
          compareKeywords={compareData?.keywords ?? {}}
          compareAppIds={compareAppIds}
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
}: {
  keywords: KeywordDensityResource[]
  compareApps: KeywordCompareResourceAppsItem[]
  compareKeywords: KeywordCompareResourceKeywords
  compareAppIds: number[]
}) {
  const [sorting, setSorting] = useState<SortingState>([])
  const [globalFilter, setGlobalFilter] = useState('')
  const tableContainerRef = useRef<HTMLDivElement>(null)
  const [visibleRows, setVisibleRows] = useState(100)

  const getCompareData = useCallback(
    (keyword: string, appId: number): { count: number; density: number } | null => {
      const raw = compareKeywords[String(appId)]?.[keyword]
      if (!raw) return null
      return { count: raw.count ?? 0, density: raw.density ?? 0 }
    },
    [compareKeywords],
  )

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

  const columns: ColumnDef<KeywordRow>[] = useMemo(() => {
    const SortIcon = ({ column }: { column: { getIsSorted: () => false | 'asc' | 'desc' } }) => {
      const s = column.getIsSorted()
      if (s === 'asc') return <ArrowUp className="h-3 w-3" />
      if (s === 'desc') return <ArrowDown className="h-3 w-3" />
      return <ArrowUpDown className="h-3 w-3 opacity-30" />
    }

    const cols: ColumnDef<KeywordRow>[] = [
      {
        accessorKey: 'keyword',
        header: ({ column }) => (
          <button className="flex items-center gap-1" onClick={() => column.toggleSorting()}>
            Keyword <SortIcon column={column} />
          </button>
        ),
        cell: ({ getValue }) => <span className="font-mono text-xs">{getValue() as string}</span>,
      },
      {
        accessorKey: 'density',
        header: ({ column }) => (
          <button className="ml-auto flex items-center gap-1" onClick={() => column.toggleSorting()}>
            Density <SortIcon column={column} />
          </button>
        ),
        cell: ({ row, getValue }) => {
          const d = getValue() as number
          const count = row.original.count
          return (
            <div className={`text-right text-xs font-medium ${getDensityColor(d)}`}>
              {count > 0 && <span className="font-normal text-muted-foreground">({count})</span>}{' '}
              {d > 0 ? `${d}%` : '—'}
            </div>
          )
        },
      },
    ]

    compareAppIds.forEach((id) => {
      const app = compareApps.find((a) => a.id === id)
      const name = app?.name ?? `App #${id}`

      cols.push({
        accessorKey: `comp_${id}`,
        header: ({ column }) => (
          <button className="ml-auto flex items-center gap-1.5 truncate max-w-[150px]" title={name} onClick={() => column.toggleSorting()}>
            {app?.icon_url && (
              <img src={app.icon_url} alt="" className="h-4 w-4 shrink-0 rounded-sm object-cover" />
            )}
            <span className="truncate">{name}</span>
            <SortIcon column={column} />
          </button>
        ),
        cell: ({ getValue }) => {
          const cd = getValue() as { count: number; density: number } | null
          return (
            <div className={`text-right text-xs font-medium ${cd ? getDensityColor(cd.density) : 'text-muted-foreground/30'}`}>
              {cd ? <><span className="font-normal text-muted-foreground">({cd.count})</span> {cd.density}%</> : '—'}
            </div>
          )
        },
        sortingFn: (a, b, columnId) => {
          const av = (a.getValue(columnId) as { density: number } | null)?.density ?? -1
          const bv = (b.getValue(columnId) as { density: number } | null)?.density ?? -1
          return av - bv
        },
      })
    })

    return cols
  }, [compareAppIds, compareApps])

  const table = useReactTable({
    data: tableData,
    columns,
    state: { sorting, globalFilter },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    globalFilterFn: (row, _columnId, filterValue) => {
      return (row.getValue('keyword') as string).includes(filterValue.toLowerCase())
    },
  })

  const filteredRows = table.getFilteredRowModel().rows
  const sortedRows = table.getRowModel().rows
  const displayedRows = sortedRows.slice(0, visibleRows)

  // Infinite scroll
  useEffect(() => {
    const container = tableContainerRef.current
    if (!container) return

    const handleScroll = () => {
      const { scrollTop, scrollHeight, clientHeight } = container
      if (scrollHeight - scrollTop - clientHeight < 200) {
        setVisibleRows((prev) => Math.min(prev + 100, sortedRows.length))
      }
    }

    container.addEventListener('scroll', handleScroll)
    return () => container.removeEventListener('scroll', handleScroll)
  }, [sortedRows.length])

  // Reset visible rows when data changes
  useEffect(() => {
    setVisibleRows(100)
  }, [tableData, globalFilter, sorting])

  return (
    <Card>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <CardTitle className="text-sm font-medium">
            {filteredRows.length} keyword{filteredRows.length !== 1 ? 's' : ''}
          </CardTitle>
          <div className="relative w-[200px]">
            <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <Input
              value={globalFilter}
              onChange={(e) => setGlobalFilter(e.target.value)}
              placeholder="Filter keywords..."
              className="h-8 pl-8 text-xs"
            />
          </div>
        </div>
      </CardHeader>
      <CardContent>
        <div ref={tableContainerRef} className="max-h-[600px] overflow-auto">
          <table className="w-full text-sm">
            <thead className="sticky top-0 z-10 bg-card">
              {table.getHeaderGroups().map((hg) => (
                <tr key={hg.id} className="border-b text-xs text-muted-foreground">
                  {hg.headers.map((header) => (
                    <th key={header.id} className="pb-2 font-medium">
                      {flexRender(header.column.columnDef.header, header.getContext())}
                    </th>
                  ))}
                </tr>
              ))}
            </thead>
            <tbody>
              {displayedRows.map((row) => (
                <tr key={row.id} className="border-b border-border/50 last:border-0">
                  {row.getVisibleCells().map((cell) => (
                    <td key={cell.id} className="py-1.5">
                      {flexRender(cell.column.columnDef.cell, cell.getContext())}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
          {displayedRows.length < sortedRows.length && (
            <div className="py-3 text-center text-xs text-muted-foreground">
              Showing {displayedRows.length} of {filteredRows.length} keywords — scroll for more
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
