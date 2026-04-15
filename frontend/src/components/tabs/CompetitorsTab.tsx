import { useState, useEffect, useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import axios from '@/lib/axios'
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

interface CompetitorApp {
  id: number
  name: string
  platform: string
  external_id: string
  publisher: { id: number; name: string; url?: string } | null
  category: { id: number; name: string; slug: string } | null
  icon_url: string | null
  rating: number | null
  rating_count: number | null
  version: string | null
  last_build_at: string | null
  created_at: string
}

interface Competitor {
  id: number
  relationship: string
  app: CompetitorApp
  created_at: string
}

interface CompetitorsTabProps {
  competitors: Competitor[]
  platform: string
  externalId: string
  isTracked: boolean
  onTrack: () => void
  trackLoading: boolean
}

interface SearchResult {
  external_id: string
  name: string
  publisher_name: string
  icon_url: string | null
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
  const [searchResults, setSearchResults] = useState<SearchResult[]>([])
  const [searching, setSearching] = useState(false)
  const [selectedExternalId, setSelectedExternalId] = useState('')
  const [relationship, setRelationship] = useState('direct')
  const [submitting, setSubmitting] = useState(false)
  const [deleting, setDeleting] = useState<number | null>(null)

  const search = useCallback(async (term: string) => {
    if (term.length < 2) {
      setSearchResults([])
      return
    }
    setSearching(true)
    try {
      const { data } = await axios.get(`/apps/search?term=${encodeURIComponent(term)}&platform=${platform}`)
      setSearchResults(data)
    } catch {
      setSearchResults([])
    } finally {
      setSearching(false)
    }
  }, [platform])

  useEffect(() => {
    if (selectedExternalId) return
    const timeout = setTimeout(() => search(searchQuery), 400)
    return () => clearTimeout(timeout)
  }, [searchQuery, search, selectedExternalId])

  const handleSubmit = async () => {
    if (!selectedExternalId) return
    setSubmitting(true)
    try {
      // First register the competitor app
      const { data: registeredApp } = await axios.post('/apps', {
        external_id: selectedExternalId,
        platform,
        is_direct: false,
      })

      // Then add as competitor
      await axios.post(`/apps/${platform}/${externalId}/competitors`, {
        competitor_app_id: registeredApp.id,
        relationship,
      })

      queryClient.invalidateQueries({ queryKey: ['apps', platform, externalId] })
      queryClient.invalidateQueries({ queryKey: ['competitor-apps', platform, externalId] })
      resetForm()
      setOpen(false)
    } catch {
      // ignore
    } finally {
      setSubmitting(false)
    }
  }

  const handleDelete = async (competitorId: number) => {
    setDeleting(competitorId)
    try {
      await axios.delete(`/apps/${platform}/${externalId}/competitors/${competitorId}`)
      queryClient.invalidateQueries({ queryKey: ['apps', platform, externalId] })
      queryClient.invalidateQueries({ queryKey: ['competitor-apps', platform, externalId] })
    } catch {
      // ignore
    } finally {
      setDeleting(null)
    }
  }

  const resetForm = () => {
    setSearchQuery('')
    setSearchResults([])
    setSelectedExternalId('')
    setRelationship('direct')
  }

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
                      {((result as Record<string, any>).publisher?.name || '—').slice(0, 30)}
                    </p>
                  </div>
                </button>
              ))}
            </div>
          )}

          <div className="space-y-2">
            <Label>Relationship</Label>
            <Select value={relationship} onValueChange={(v) => v && setRelationship(v)}>
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
              disabled={deleting === competitor.id}
            >
              {deleting === competitor.id ? (
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
