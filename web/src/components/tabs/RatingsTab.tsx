import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ReferenceLine,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { ArrowDown, ArrowUp, Minus, Star, TrendingUp } from 'lucide-react'
import axios from '@/lib/axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import QueryError from '@/components/QueryError'
import { useCountries } from '@/components/CountrySelect'

interface RatingsTabProps {
  platform: string
  externalId: string
}

type RatingBreakdown = {
  '1': number
  '2': number
  '3': number
  '4': number
  '5': number
}

interface RatingSummary {
  rating: number
  rating_count: number
  breakdown: RatingBreakdown
  trend: {
    rating_delta_30d: number | null
    rating_count_delta_30d: number | null
  }
}

interface RatingBreakdownBucket {
  '1': number
  '2': number
  '3': number
  '4': number
  '5': number
}

interface RatingHistoryPoint {
  date: string
  rating: number | null
  rating_count: number | null
  breakdown: RatingBreakdownBucket
  delta_breakdown: RatingBreakdownBucket | null
  delta_total: number | null
}

interface RatingByCountry {
  country_code: string
  rating: number | null
  rating_count: number | null
}

type RatingHistoryResponse = RatingHistoryPoint[] | { data: RatingHistoryPoint[] }

interface RatingCountryBreakdownResponse {
  data: RatingByCountry[]
  supported?: boolean
  message?: string
}

function flagUrl(code: string): string {
  return `https://flagcdn.com/w40/${code}.png`
}

function formatNumber(n: number | null | undefined): string {
  if (n === null || n === undefined) return '\u2014'
  if (Math.abs(n) >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
  if (Math.abs(n) >= 1_000) return `${(n / 1_000).toFixed(1)}K`
  return n.toString()
}

function formatDay(dateStr: string): string {
  // date format: YYYY-MM-DD
  const [y, m, d] = dateStr.split('-')
  if (!y || !m || !d) return dateStr
  const date = new Date(Number(y), Number(m) - 1, Number(d))
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

export default function RatingsTab({ platform, externalId }: RatingsTabProps) {
  const summaryQuery = useQuery<RatingSummary>({
    queryKey: ['ratings-summary', platform, externalId],
    queryFn: () =>
      axios
        .get(`/apps/${platform}/${externalId}/ratings/summary`)
        .then((r) => r.data),
  })

  const historyQuery = useQuery<RatingHistoryResponse>({
    queryKey: ['ratings-history', platform, externalId, 30],
    queryFn: () =>
      axios
        .get(`/apps/${platform}/${externalId}/ratings/history`, {
          params: { days: 30 },
        })
        .then((r) => r.data),
  })

  const countryQuery = useQuery<RatingCountryBreakdownResponse>({
    queryKey: ['ratings-country-breakdown', platform, externalId],
    queryFn: () =>
      axios
        .get(`/apps/${platform}/${externalId}/ratings/country-breakdown`)
        .then((r) => r.data),
  })

  const isLoading =
    summaryQuery.isLoading || historyQuery.isLoading || countryQuery.isLoading
  const isError =
    summaryQuery.isError || historyQuery.isError || countryQuery.isError

  if (isLoading) {
    return (
      <div className="flex flex-col gap-6">
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          <Skeleton className="h-48" />
          <Skeleton className="h-48" />
        </div>
        <Skeleton className="h-72" />
        <Skeleton className="h-64" />
      </div>
    )
  }

  if (isError) {
    return (
      <QueryError
        message="Failed to load ratings."
        onRetry={() => {
          summaryQuery.refetch()
          historyQuery.refetch()
          countryQuery.refetch()
        }}
      />
    )
  }

  const summary = summaryQuery.data
  const historyData = historyQuery.data
  const history: RatingHistoryPoint[] = Array.isArray(historyData)
    ? historyData
    : historyData?.data ?? []
  const countryData = countryQuery.data

  return (
    <div className="flex flex-col gap-6">
      {summary && <RatingsSummaryCards summary={summary} />}

      <NewReviewsChart points={history} />
      <RatingsHistoryChart points={history} />

      <RatingsCountryBreakdown platform={platform} response={countryData} />
    </div>
  )
}

// ---------- Summary Cards ----------

function RatingsSummaryCards({ summary }: { summary: RatingSummary }) {
  return (
    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
      <CurrentRatingCard summary={summary} />
      <RatingTrendCard summary={summary} />
    </div>
  )
}

function CurrentRatingCard({ summary }: { summary: RatingSummary }) {
  const hasRatings = summary.rating_count > 0
  const average = Number(summary.rating) || 0
  const maxCount = useMemo(() => {
    const values = Object.values(summary.breakdown ?? {}) as number[]
    const max = Math.max(...values, 0)
    return max
  }, [summary.breakdown])

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Current Rating</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:gap-6">
          <div className="flex flex-col items-start gap-1">
            <div className="flex items-baseline gap-2">
              <span className="text-3xl font-bold tabular-nums sm:text-4xl">
                {hasRatings ? average.toFixed(2) : '0.00'}
              </span>
              <Star
                className={`h-6 w-6 ${hasRatings ? 'fill-yellow-500 text-yellow-500' : 'text-muted-foreground/40'}`}
              />
            </div>
            <p className="text-xs text-muted-foreground">
              {hasRatings
                ? `${formatNumber(summary.rating_count)} ratings`
                : 'No ratings yet'}
            </p>
          </div>

          <div className="flex-1 space-y-1">
            {[5, 4, 3, 2, 1].map((star) => {
              const count =
                (summary.breakdown?.[String(star) as keyof RatingBreakdown] as number) ?? 0
              const pct = maxCount > 0 ? (count / maxCount) * 100 : 0
              return (
                <div key={star} className="flex items-center gap-2 text-xs">
                  <span className="w-6 shrink-0 text-right font-medium text-muted-foreground">
                    {star}
                    <span className="text-yellow-500">★</span>
                  </span>
                  <div className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-yellow-500/80"
                      style={{ width: `${pct}%` }}
                    />
                  </div>
                  <span className="w-10 shrink-0 text-right tabular-nums text-muted-foreground">
                    {formatNumber(count)}
                  </span>
                </div>
              )
            })}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

function RatingTrendCard({ summary }: { summary: RatingSummary }) {
  const ratingDelta = summary.trend?.rating_delta_30d ?? null
  const countDelta = summary.trend?.rating_count_delta_30d ?? null

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Rating Trend</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="mb-4 text-xs text-muted-foreground">Last 30 days</p>
        <div className="grid grid-cols-2 gap-4">
          <TrendCell
            label="Rating"
            value={ratingDelta === null ? '\u2014' : ratingDelta.toFixed(2)}
            delta={ratingDelta}
            precision={2}
          />
          <TrendCell
            label="Total Ratings"
            value={countDelta === null ? '\u2014' : formatNumber(countDelta)}
            delta={countDelta}
            precision={0}
          />
        </div>
      </CardContent>
    </Card>
  )
}

function TrendCell({
  label,
  value,
  delta,
  precision,
}: {
  label: string
  value: string
  delta: number | null
  precision: number
}) {
  const icon =
    delta === null ? (
      <Minus className="h-4 w-4 text-muted-foreground" />
    ) : delta > 0 ? (
      <ArrowUp className="h-4 w-4 text-emerald-500" />
    ) : delta < 0 ? (
      <ArrowDown className="h-4 w-4 text-red-500" />
    ) : (
      <Minus className="h-4 w-4 text-muted-foreground" />
    )

  const color =
    delta === null || delta === 0
      ? 'text-muted-foreground'
      : delta > 0
        ? 'text-emerald-500'
        : 'text-red-500'

  const signedDelta =
    delta === null
      ? '\u2014'
      : `${delta > 0 ? '+' : ''}${precision > 0 ? delta.toFixed(precision) : Math.round(delta).toString()}`

  return (
    <div className="flex flex-col gap-1">
      <span className="text-xs text-muted-foreground">{label}</span>
      <div className="flex items-center gap-2">
        {icon}
        <span className={`text-xl font-bold tabular-nums sm:text-2xl ${color}`}>
          {value}
        </span>
      </div>
      <span className={`text-xs tabular-nums ${color}`}>
        {delta === null ? '\u2014' : `${signedDelta} vs 30 days ago`}
      </span>
    </div>
  )
}

// ---------- New Reviews Chart (daily stacked bar) ----------

const STAR_COLORS: Record<'1' | '2' | '3' | '4' | '5', string> = {
  '1': '#ef4444',
  '2': '#f97316',
  '3': '#eab308',
  '4': '#84cc16',
  '5': '#22c55e',
}

type StarKey = '1' | '2' | '3' | '4' | '5'

function NewReviewsChart({ points }: { points: RatingHistoryPoint[] }) {
  const [hidden, setHidden] = useState<Set<StarKey>>(new Set())

  const chartData = useMemo(
    () =>
      points.map((p) => ({
        date: p.date,
        label: formatDay(p.date),
        star1: p.delta_breakdown?.['1'] ?? null,
        star2: p.delta_breakdown?.['2'] ?? null,
        star3: p.delta_breakdown?.['3'] ?? null,
        star4: p.delta_breakdown?.['4'] ?? null,
        star5: p.delta_breakdown?.['5'] ?? null,
        total: p.delta_total,
      })),
    [points],
  )

  const hasAnyDelta = chartData.some((p) =>
    [p.star1, p.star2, p.star3, p.star4, p.star5].some((v) => v !== null && v !== 0),
  )

  const tickInterval = Math.max(1, Math.floor(chartData.length / 6)) - 1

  const toggle = (key: StarKey) => {
    setHidden((prev) => {
      const next = new Set(prev)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Daily Rating Changes</CardTitle>
        <p className="text-xs text-muted-foreground">
          Per-star net change — last 30 days
        </p>
      </CardHeader>
      <CardContent>
        {!hasAnyDelta ? (
          <div className="flex flex-col items-center justify-center gap-2 py-12">
            <TrendingUp className="h-8 w-8 text-muted-foreground/50" />
            <p className="text-sm text-muted-foreground">
              Not enough history yet to compute daily changes.
            </p>
          </div>
        ) : (
          <>
            <div className="h-56">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart
                  data={chartData}
                  margin={{ top: 10, right: 12, left: 0, bottom: 0 }}
                >
                  <CartesianGrid
                    strokeDasharray="3 3"
                    className="stroke-border"
                    vertical={false}
                  />
                  <XAxis
                    dataKey="label"
                    tick={{ fontSize: 12 }}
                    interval={tickInterval}
                    className="text-muted-foreground"
                  />
                  <YAxis
                    tick={{ fontSize: 12 }}
                    className="text-muted-foreground"
                    domain={['auto', 'auto']}
                  />
                  <Tooltip content={<NewReviewsTooltip />} />
                  <Legend content={() => null} />
                  <ReferenceLine y={0} stroke="var(--color-border)" />
                  {(['1', '2', '3', '4', '5'] as StarKey[]).map((k) =>
                    hidden.has(k) ? null : (
                      <Bar
                        key={k}
                        dataKey={`star${k}`}
                        name={`${k}★`}
                        stackId="stars"
                        fill={STAR_COLORS[k]}
                      />
                    ),
                  )}
                </BarChart>
              </ResponsiveContainer>
            </div>
            <div className="mt-3 flex flex-wrap items-center justify-center gap-2">
              {(['5', '4', '3', '2', '1'] as StarKey[]).map((k) => {
                const off = hidden.has(k)
                return (
                  <button
                    key={k}
                    type="button"
                    onClick={() => toggle(k)}
                    className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition-colors ${
                      off
                        ? 'border-border/40 text-muted-foreground/50'
                        : 'border-border text-foreground hover:bg-accent/30'
                    }`}
                  >
                    <span
                      className="h-2.5 w-2.5 rounded-full"
                      style={{ backgroundColor: off ? 'transparent' : STAR_COLORS[k], borderColor: STAR_COLORS[k], borderWidth: off ? 1 : 0, borderStyle: 'solid' }}
                    />
                    {k}★
                  </button>
                )
              })}
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}

type NewReviewsTooltipPayload = {
  name: string
  value: number | null
  color: string
}

function formatSigned(n: number): string {
  if (n > 0) return `+${n}`
  return n.toString()
}

function NewReviewsTooltip({
  active,
  payload,
  label,
}: {
  active?: boolean
  payload?: NewReviewsTooltipPayload[]
  label?: string
}) {
  if (!active || !payload || payload.length === 0) return null
  const total = payload.reduce((acc, p) => acc + (p.value ?? 0), 0)
  return (
    <div className="rounded-lg border bg-popover p-2 text-xs text-popover-foreground shadow-md">
      <p className="mb-1 font-medium">{label}</p>
      {[...payload].reverse().map((p) => (
        <p key={p.name} className="flex items-center gap-1.5">
          <span
            className="h-2 w-2 rounded-full"
            style={{ backgroundColor: p.color }}
          />
          <span>{p.name}</span>
          <span
            className={`ml-auto font-medium tabular-nums ${
              (p.value ?? 0) < 0 ? 'text-red-500' : ''
            }`}
          >
            {formatSigned(p.value ?? 0)}
          </span>
        </p>
      ))}
      <p className="mt-1 border-t pt-1 flex justify-between font-medium">
        <span>Net</span>
        <span
          className={`tabular-nums ${total < 0 ? 'text-red-500' : ''}`}
        >
          {formatSigned(total)}
        </span>
      </p>
    </div>
  )
}

// ---------- Average Rating Chart ----------

function RatingsHistoryChart({ points }: { points: RatingHistoryPoint[] }) {
  const hasAnyValue = points.some((p) => p.rating !== null)

  const chartData = useMemo(
    () =>
      points.map((p) => ({
        date: p.date,
        label: formatDay(p.date),
        rating: p.rating,
        rating_count: p.rating_count,
      })),
    [points],
  )

  // Show every ~5th tick so the X axis stays legible on a 30-day chart.
  const tickInterval = Math.max(1, Math.floor(chartData.length / 6)) - 1

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Average Rating</CardTitle>
        <p className="text-xs text-muted-foreground">Daily average — last 30 days</p>
      </CardHeader>
      <CardContent>
        {!hasAnyValue ? (
          <div className="flex flex-col items-center justify-center gap-2 py-12">
            <TrendingUp className="h-8 w-8 text-muted-foreground/50" />
            <p className="text-sm text-muted-foreground">
              No rating history yet.
            </p>
          </div>
        ) : (
          <div className="h-56">
            <ResponsiveContainer width="100%" height="100%">
              <LineChart
                data={chartData}
                margin={{ top: 10, right: 12, left: 0, bottom: 0 }}
              >
                <CartesianGrid
                  strokeDasharray="3 3"
                  className="stroke-border"
                  vertical={false}
                />
                <XAxis
                  dataKey="label"
                  tick={{ fontSize: 12 }}
                  interval={tickInterval}
                  className="text-muted-foreground"
                />
                <YAxis
                  domain={[0, 5]}
                  tick={{ fontSize: 12 }}
                  className="text-muted-foreground"
                />
                <Tooltip content={<ChartTooltip />} />
                <Line
                  type="monotone"
                  dataKey="rating"
                  stroke="var(--color-primary)"
                  strokeWidth={2}
                  dot={{ r: 3 }}
                  activeDot={{ r: 5 }}
                  connectNulls
                />
              </LineChart>
            </ResponsiveContainer>
          </div>
        )}
      </CardContent>
    </Card>
  )
}

type TooltipPayload = {
  payload: {
    label: string
    rating: number | null
    rating_count: number | null
  }
}

function ChartTooltip({
  active,
  payload,
}: {
  active?: boolean
  payload?: TooltipPayload[]
}) {
  if (!active || !payload || payload.length === 0) return null
  const point = payload[0].payload
  return (
    <div className="rounded-lg border bg-popover p-2 text-xs text-popover-foreground shadow-md">
      <p className="mb-1 font-medium">{point.label}</p>
      <p className="flex items-center gap-1">
        <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
        <span className="tabular-nums">
          {point.rating !== null ? Number(point.rating).toFixed(2) : '\u2014'}
        </span>
      </p>
      <p className="text-muted-foreground tabular-nums">
        {formatNumber(point.rating_count)} ratings
      </p>
    </div>
  )
}

// ---------- Country Breakdown ----------

function RatingsCountryBreakdown({
  platform,
  response,
}: {
  platform: string
  response: RatingCountryBreakdownResponse | undefined
}) {
  if (platform === 'android' || response?.supported === false) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Country Breakdown</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <p className="text-sm text-muted-foreground">
              {response?.message ??
                'Google Play does not provide ratings data by country.'}
            </p>
          </div>
        </CardContent>
      </Card>
    )
  }

  const rows = response?.data ?? []

  if (rows.length === 0) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Country Breakdown</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <p className="text-sm text-muted-foreground">
              No country-level ratings data available yet.
            </p>
          </div>
        </CardContent>
      </Card>
    )
  }

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <CountryTable
        title="Top Rated"
        subtitle="Countries with the highest average rating"
        rows={rows}
        metric="rating"
      />
      <CountryTable
        title="Most Total Ratings"
        subtitle="Countries with the most ratings"
        rows={rows}
        metric="rating_count"
      />
    </div>
  )
}

function CountryTable({
  title,
  subtitle,
  rows,
  metric,
}: {
  title: string
  subtitle: string
  rows: RatingByCountry[]
  metric: 'rating' | 'rating_count'
}) {
  const { data: countries } = useCountries()

  const sorted = useMemo(() => {
    const copy = [...rows]
    copy.sort((a, b) => {
      const av = a[metric]
      const bv = b[metric]
      // Nulls / zeros last.
      const aEmpty = av === null || av === 0
      const bEmpty = bv === null || bv === 0
      if (aEmpty && !bEmpty) return 1
      if (!aEmpty && bEmpty) return -1
      if (aEmpty && bEmpty) return 0
      return (bv as number) - (av as number)
    })
    return copy
  }, [rows, metric])

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{title}</CardTitle>
        <p className="text-xs text-muted-foreground">{subtitle}</p>
      </CardHeader>
      <CardContent>
        <div className="divide-y">
          {sorted.map((row, i) => {
            const country = countries?.find((c) => c.code === row.country_code)
            const name = country?.name ?? row.country_code.toUpperCase()
            return (
              <div
                key={row.country_code}
                className="flex items-center gap-3 py-2 text-sm"
              >
                <span className="w-5 shrink-0 text-right text-xs text-muted-foreground tabular-nums">
                  {i + 1}.
                </span>
                <img
                  src={flagUrl(row.country_code)}
                  alt=""
                  className="h-3.5 w-5 shrink-0 rounded-[2px] object-cover"
                />
                <span className="flex-1 truncate">{name}</span>
                {metric === 'rating' ? (
                  <span className="flex items-center gap-1 tabular-nums">
                    <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
                    <span className="font-medium">
                      {row.rating !== null
                        ? Number(row.rating).toFixed(2)
                        : '\u2014'}
                    </span>
                  </span>
                ) : (
                  <span className="font-medium tabular-nums">
                    {formatNumber(row.rating_count)}
                  </span>
                )}
              </div>
            )
          })}
        </div>
      </CardContent>
    </Card>
  )
}
