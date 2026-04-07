import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import axios from '@/lib/axios'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MessageSquareText, Star, ChevronLeft, ChevronRight } from 'lucide-react'

interface ReviewData {
  id: number
  country_code: string
  author: string | null
  title: string | null
  body: string | null
  rating: number
  review_date: string | null
  app_version: string | null
}

interface ReviewSummary {
  total_reviews: number
  average_rating: number
  distribution: Record<string, number>
  countries: string[]
}

interface ReviewsTabProps {
  platform: string
  externalId: string
  currentRating: number | null
  currentRatingCount: number | null
  selectedCountry: string
}

function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {Array.from({ length: 5 }, (_, i) => (
        <Star
          key={i}
          className={`h-3.5 w-3.5 ${
            i < rating
              ? 'fill-yellow-500 text-yellow-500'
              : 'text-muted-foreground/30'
          }`}
        />
      ))}
    </div>
  )
}

function RatingBar({ star, count, max }: { star: number; count: number; max: number }) {
  const pct = max > 0 ? (count / max) * 100 : 0

  return (
    <div className="flex items-center gap-3">
      <span className="flex w-12 items-center gap-1 text-sm text-muted-foreground">
        {star}
        <Star className="h-3 w-3 fill-yellow-500 text-yellow-500" />
      </span>
      <div className="h-2.5 flex-1 overflow-hidden rounded-full bg-muted">
        <div
          className="h-full rounded-full bg-yellow-500 transition-all"
          style={{ width: `${pct}%` }}
        />
      </div>
      <span className="w-12 text-right text-sm tabular-nums text-muted-foreground">
        {count}
      </span>
    </div>
  )
}


export default function ReviewsTab({ platform, externalId, currentRating, currentRatingCount, selectedCountry }: ReviewsTabProps) {
  const countryCode = selectedCountry
  const [rating, setRating] = useState<string>('all')
  const [sort, setSort] = useState<string>('latest')
  const [page, setPage] = useState(1)

  const { data: summary } = useQuery<ReviewSummary>({
    queryKey: ['reviews-summary', platform, externalId, countryCode],
    queryFn: () => axios.get(`/apps/${platform}/${externalId}/reviews/summary`, { params: { country_code: countryCode } }).then((r) => r.data),
  })

  const { data: reviewsResponse, isLoading } = useQuery({
    queryKey: ['reviews', platform, externalId, countryCode, rating, sort, page],
    queryFn: () =>
      axios
        .get(`/apps/${platform}/${externalId}/reviews`, {
          params: {
            country_code: countryCode,
            rating: rating !== 'all' ? rating : undefined,
            sort,
            page,
            per_page: 25,
          },
        })
        .then((r) => r.data),
  })

  const reviews: ReviewData[] = reviewsResponse?.data ?? []
  const lastPage: number = reviewsResponse?.meta?.last_page ?? 1

  const hasReviewData = summary && summary.total_reviews > 0
  const summaryRating = summary?.average_rating ?? currentRating ?? 0
  const summaryTotal = summary?.total_reviews ?? 0
  const hasStoreRating = summaryRating > 0

  if (!hasReviewData && !hasStoreRating) {
    return (
      <div className="py-12 text-center">
        <MessageSquareText className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No ratings or reviews yet.</p>
        <p className="mt-1 text-sm text-muted-foreground/70">
          Data will appear here once fetched from the store.
        </p>
      </div>
    )
  }

  const dist = summary?.distribution ?? {}
  const maxCount = Math.max(...Object.values(dist), 0)

  const ratingLabel = (r: string) => {
    if (r === 'all') return 'All Ratings'
    return `${r} Star${Number(r) > 1 ? 's' : ''}`
  }

  return (
    <div className="space-y-6">
      {/* Rating summary cards */}
      <div className="grid gap-6 md:grid-cols-2">
        {/* Store Rating */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Store Rating</CardTitle>
          </CardHeader>
          <CardContent>
            {hasStoreRating ? (
              <div className="flex items-baseline gap-3">
                <span className="text-5xl font-bold tabular-nums">
                  {summaryRating.toFixed(1)}
                </span>
                <div className="space-y-1">
                  <div className="flex items-center gap-0.5">
                    {Array.from({ length: 5 }, (_, i) => (
                      <Star
                        key={i}
                        className={`h-4 w-4 ${
                          i < Math.round(summaryRating)
                            ? 'fill-yellow-500 text-yellow-500'
                            : 'text-muted-foreground/30'
                        }`}
                      />
                    ))}
                  </div>
                  {summaryTotal > 0 && (
                    <p className="text-sm text-muted-foreground">
                      {summaryTotal.toLocaleString()} reviews
                    </p>
                  )}
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">
                No store rating available.
              </p>
            )}
          </CardContent>
        </Card>

        {/* Review Distribution */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Review Distribution</CardTitle>
          </CardHeader>
          <CardContent>
            {hasReviewData ? (
              <div className="space-y-2">
                {[5, 4, 3, 2, 1].map((star) => (
                  <RatingBar
                    key={star}
                    star={star}
                    count={dist[star] ?? 0}
                    max={maxCount}
                  />
                ))}
                <div className="mt-4 flex items-baseline gap-2 border-t pt-3">
                  <span className="text-2xl font-semibold tabular-nums">
                    {summary!.average_rating.toFixed(2)}
                  </span>
                  <span className="text-sm text-muted-foreground">
                    avg from {summary!.total_reviews} review
                    {summary!.total_reviews !== 1 ? 's' : ''}
                  </span>
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">
                No review distribution data yet.
              </p>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      {hasReviewData && (
        <>
          <div className="flex flex-wrap items-center gap-3">
            <Select value={rating} onValueChange={(v) => { if (v) { setRating(v); setPage(1) } }}>
              <SelectTrigger className="w-[130px]">
                <SelectValue>{ratingLabel(rating)}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Ratings</SelectItem>
                {[5, 4, 3, 2, 1].map((r) => (
                  <SelectItem key={r} value={String(r)}>
                    {r} Star{r > 1 ? 's' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={sort} onValueChange={(v) => { if (v) { setSort(v); setPage(1) } }}>
              <SelectTrigger className="w-[130px]">
                <SelectValue>
                  {{ latest: 'Latest', oldest: 'Oldest', highest: 'Highest', lowest: 'Lowest' }[sort]}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="latest">Latest</SelectItem>
                <SelectItem value="oldest">Oldest</SelectItem>
                <SelectItem value="highest">Highest</SelectItem>
                <SelectItem value="lowest">Lowest</SelectItem>
              </SelectContent>
            </Select>

            <span className="text-sm text-muted-foreground">
              {summary!.total_reviews} review{summary!.total_reviews !== 1 ? 's' : ''}
            </span>
          </div>

          {/* Review list */}
          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <div className="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
            </div>
          ) : (
            <div className="space-y-3">
              {reviews.map((review) => (
                <Card key={review.id}>
                  <CardContent className="pt-4">
                    <div className="flex items-start justify-between gap-4">
                      <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                          <StarRating rating={review.rating} />
                          {review.title && (
                            <span className="truncate text-sm font-medium">
                              {review.title}
                            </span>
                          )}
                        </div>
                        {review.body && (
                          <p className="text-sm text-muted-foreground">{review.body}</p>
                        )}
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground/70">
                          {review.author && <span>{review.author}</span>}
                          {review.review_date && <span>{review.review_date}</span>}
                          {review.app_version && (
                            <Badge variant="outline" className="text-[10px]">
                              v{review.app_version}
                            </Badge>
                          )}
                          {review.country_code && (
                            <Badge variant="secondary" className="text-[10px]">
                              {review.country_code.toUpperCase()}
                            </Badge>
                          )}
                        </div>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}

          {/* Pagination */}
          {lastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page === 1}
              >
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <span className="text-sm text-muted-foreground">
                {page} / {lastPage}
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
                disabled={page === lastPage}
              >
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
