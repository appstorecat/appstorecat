import { useState, useEffect, useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { BookmarkPlus, Plus, Search, Loader2, Trash2, Users } from 'lucide-react'
import {
  useSearchApps,
  useStoreCompetitor,
  useDeleteCompetitor,
  getListCompetitorsQueryKey,
  getShowAppQueryKey,
} from '@/api/endpoints/apps/apps'
import type { CompetitorResource } from '@/api/models/competitorResource'
import type { SearchAppsPlatform } from '@/api/models/searchAppsPlatform'
import { StoreCompetitorRequestRelationship } from '@/api/models/storeCompetitorRequestRelationship'

interface CompetitorsTabProps {
  competitors: CompetitorResource[]
  platform: string
  externalId: string
  isTracked: boolean
  onTrack: () => void
  trackLoading: boolean
}

const relationshipLabels: Record<string, string> = {
  direct: 'Direct',
  indirect: 'Indirect',
  aspiration: 'Aspiration',
}

export default function CompetitorsTab({ competitors, platform, externalId, isTracked, onTrack, trackLoading }: CompetitorsTabProps) {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [debouncedTerm, setDebouncedTerm] = useState('')
  const [selectedExternalId, setSelectedExternalId] = useState('')
  const [relationship, setRelationship] = useState<StoreCompetitorRequestRelationship>(
    StoreCompetitorRequestRelationship.direct,
  )

  // Platform comes from the parent route as a plain string. Backend enum is
  // lowercase 'ios' | 'android'; coerce at the boundary without widening.
  const platformParam = platform as SearchAppsPlatform
  const platformForUrl = platform as 'ios' | 'android'

  useEffect(() => {
    if (selectedExternalId) return
    const handle = setTimeout(() => setDebouncedTerm(searchQuery.trim()), 400)
    return () => clearTimeout(handle)
  }, [searchQuery, selectedExternalId])

  const searchEnabled = debouncedTerm.length >= 2 && !selectedExternalId
  // Backend filters out the parent app and already-added competitors via
  // `exclude_external_ids[]` so the picker never shows duplicates (which would
  // otherwise 422 on submit).
  const excludeExternalIds = [
    externalId,
    ...competitors.map((c) => c.app?.external_id).filter((id): id is string => Boolean(id)),
  ]
  const { data: searchResults = [], isFetching: searching } = useSearchApps(
    {
      term: debouncedTerm,
      platform: platformParam,
      'exclude_external_ids[]': excludeExternalIds,
    },
    { query: { enabled: searchEnabled } },
  )

  const storeCompetitorMutation = useStoreCompetitor()
  const deleteCompetitorMutation = useDeleteCompetitor()

  const invalidateApp = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: getShowAppQueryKey(platformForUrl, externalId) })
    queryClient.invalidateQueries({ queryKey: getListCompetitorsQueryKey(platformForUrl, externalId) })
    queryClient.invalidateQueries({ queryKey: ['apps', platform, externalId] })
    queryClient.invalidateQueries({ queryKey: ['competitor-apps', platform, externalId] })
  }, [queryClient, platformForUrl, externalId, platform])

  const handleSubmit = async () => {
    const selected = searchResults.find((r) => r.external_id === selectedExternalId)
    if (!selected) return
    try {
      await storeCompetitorMutation.mutateAsync({
        platform: platformForUrl,
        externalId,
        data: {
          competitor_app_id: selected.id,
          relationship,
        },
      })
      invalidateApp()
      resetForm()
      setOpen(false)
    } catch {
      // ignore
    }
  }

  const handleDelete = async (competitorId: number) => {
    try {
      await deleteCompetitorMutation.mutateAsync({
        platform: platformForUrl,
        externalId,
        competitor: competitorId,
      })
      invalidateApp()
    } catch {
      // ignore
    }
  }

  const resetForm = () => {
    setSearchQuery('')
    setDebouncedTerm('')
    setSelectedExternalId('')
    setRelationship(StoreCompetitorRequestRelationship.direct)
  }

  const submitting = storeCompetitorMutation.isPending
  const deletingId = deleteCompetitorMutation.isPending
    ? deleteCompetitorMutation.variables?.competitor ?? null
    : null

  if (competitors.length === 0) {
    return (
      <div className="py-12 text-center">
        <Users className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
        <p className="text-muted-foreground">No competitors added yet.</p>
        {isTracked ? (
          <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) resetForm() }}>
            <DialogTrigger render={<Button variant="outline" className="mt-4" />}>
              <Plus className="mr-2 h-4 w-4" />
              Add Competitor
            </DialogTrigger>
            {renderAddDialog()}
          </Dialog>
        ) : (
          <div className="mt-4 space-y-2">
            <p className="text-sm text-muted-foreground">Track this app to add competitors.</p>
            <Button variant="default" size="sm" onClick={onTrack} disabled={trackLoading}>
              {trackLoading ? (
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              ) : (
                <BookmarkPlus className="mr-2 h-4 w-4" />
              )}
              Track App
            </Button>
          </div>
        )}
      </div>
    )
  }

  function renderAddDialog() {
    return (
      <DialogContent className="max-w-[420px]">
        <DialogHeader>
          <DialogTitle>Add Competitor</DialogTitle>
          <DialogDescription>Search for an app to add as a competitor.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Search App</Label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search by app name..."
                className="pl-9"
              />
              {searching && <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground" />}
            </div>
          </div>

          {searchResults.length > 0 && (
            <div className="max-h-[200px] space-y-1 overflow-y-auto rounded-lg border p-1">
              {searchResults.map((result) => (
                <button
                  key={result.external_id}
                  className={`flex w-full items-center gap-3 overflow-hidden rounded-md px-2 py-2 text-left transition-colors hover:bg-muted ${
                    selectedExternalId === result.external_id ? 'bg-muted' : ''
                  }`}
                  onClick={() => setSelectedExternalId(result.external_id)}
                >
                  {result.icon_url ? (
                    <img
                      src={result.icon_url}
                      alt={result.name}
                      className="h-8 w-8 shrink-0 rounded-lg"
                      onError={(e) => { (e.target as HTMLImageElement).style.display = 'none' }}
                    />
                  ) : (
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                      <Search className="h-3 w-3 text-muted-foreground" />
                    </div>
                  )}
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">{result.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                      {(result.publisher?.name ?? result.publisher_name ?? '—').slice(0, 30)}
                    </p>
                  </div>
                </button>
              ))}
            </div>
          )}

          <div className="space-y-2">
            <Label>Relationship</Label>
            <Select
              value={relationship}
              onValueChange={(v) => v && setRelationship(v as StoreCompetitorRequestRelationship)}
            >
              <SelectTrigger><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="direct">Direct</SelectItem>
                <SelectItem value="indirect">Indirect</SelectItem>
                <SelectItem value="aspiration">Aspiration</SelectItem>
              </SelectContent>
            </Select>
          </div>

        </div>

        <DialogFooter>
          <Button onClick={handleSubmit} disabled={submitting || !selectedExternalId}>
            {submitting ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Adding...</> : 'Add Competitor'}
          </Button>
        </DialogFooter>
      </DialogContent>
    )
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">{competitors.length} competitor{competitors.length !== 1 ? 's' : ''}</p>
        {isTracked && (
          <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) resetForm() }}>
            <DialogTrigger render={<Button variant="outline" size="sm" />}>
              <Plus className="mr-2 h-4 w-4" />
              Add Competitor
            </DialogTrigger>
            {renderAddDialog()}
          </Dialog>
        )}
      </div>

      <div className="grid gap-3 md:grid-cols-2">
        {competitors.map((competitor) => (
          <div
            key={competitor.id}
            className="flex items-center gap-4 rounded-xl border p-4 transition-all hover:border-foreground/20 hover:shadow-sm"
          >
            <Link to={`/apps/${competitor.app.platform}/${competitor.app.external_id}`} className="flex flex-1 items-center gap-4 min-w-0">
              {competitor.app.icon_url ? (
                <img
                  src={competitor.app.icon_url}
                  alt={competitor.app.name}
                  className="h-12 w-12 shrink-0 rounded-xl"
                />
              ) : (
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-muted">
                  <Users className="h-5 w-5 text-muted-foreground" />
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{competitor.app.name}</p>
                <p className="truncate text-xs text-muted-foreground">
                  {competitor.app.publisher?.name ?? '—'}
                </p>
                <div className="mt-1 flex items-center gap-2">
                  <Badge variant="outline" className="text-[10px]">
                    {relationshipLabels[competitor.relationship] ?? competitor.relationship}
                  </Badge>
                </div>
              </div>
            </Link>
            <Button
              variant="ghost"
              size="icon"
              className="shrink-0 text-muted-foreground hover:text-destructive"
              onClick={() => handleDelete(competitor.id)}
              disabled={deletingId === competitor.id}
            >
              {deletingId === competitor.id ? (
                <Loader2 className="h-4 w-4 animate-spin" />
              ) : (
                <Trash2 className="h-4 w-4" />
              )}
            </Button>
          </div>
        ))}
      </div>
    </div>
  )
}
