import { type ReactNode } from 'react'
import { useSearchParams } from 'react-router-dom'
import { Search } from 'lucide-react'
import { useAppChanges } from '@/api/endpoints/change-monitor/change-monitor'
import {
  AppChangesField,
  AppChangesPlatform,
  type AppChangesParams,
  type ChangeResource,
} from '@/api/models'
import ChangeCard from '@/components/ChangeCard'
import { AppStoreSvg, GooglePlaySvg } from '@/components/PlatformSwitcher'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { useDebounce } from '@/hooks/use-debounce'

// The backend paginates this endpoint; Orval mistypes the response because the
// `#[OA\Response]` advertises a bare array. Read the real paginator envelope.
type PaginatedChanges = { data?: ChangeResource[] }

type PlatformFilter = 'all' | 'ios' | 'android'

const FIELD_LABELS: Record<string, string> = {
  title: 'Title',
  subtitle: 'Subtitle',
  description: 'Description',
  whats_new: "What's New",
  screenshots: 'Screenshots',
  locale_added: 'Locale Added',
  locale_removed: 'Locale Removed',
}

function parsePlatform(value: string | null): PlatformFilter {
  return value === 'ios' || value === 'android' ? value : 'all'
}

function parseField(value: string | null): string {
  return value && value in AppChangesField ? value : 'all'
}

export default function AppChanges() {
  const [searchParams, setSearchParams] = useSearchParams()

  const platform = parsePlatform(searchParams.get('platform'))
  const field = parseField(searchParams.get('field'))
  const searchTerm = searchParams.get('search') ?? ''
  const debouncedSearch = useDebounce(searchTerm)

  const setPlatform = (value: PlatformFilter) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value === 'all') next.delete('platform')
      else next.set('platform', value)
      return next
    }, { replace: true })
  }

  const setField = (value: string | null) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (!value || value === 'all') next.delete('field')
      else next.set('field', value)
      return next
    }, { replace: true })
  }

  const setSearchTerm = (value: string) => {
    setSearchParams((prev) => {
      const next = new URLSearchParams(prev)
      if (value) next.set('search', value)
      else next.delete('search')
      return next
    }, { replace: true })
  }

  const queryParams: AppChangesParams = { per_page: 50 }
  if (platform !== 'all') queryParams.platform = AppChangesPlatform[platform]
  if (field !== 'all') queryParams.field = AppChangesField[field as keyof typeof AppChangesField]
  if (debouncedSearch.trim().length > 0) queryParams.search = debouncedSearch.trim()

  const { data, isLoading } = useAppChanges(queryParams)
  const changes = (data as unknown as PaginatedChanges | undefined)?.data ?? []

  return (
    <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">App Changes</h1>
        <p className="text-sm text-muted-foreground">Store listing changes across your tracked apps</p>
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="relative flex-1 min-w-[240px]">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            placeholder="Search by app name..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9"
          />
        </div>

        <Select value={field} onValueChange={setField}>
          <SelectTrigger className="w-[180px]">
            <SelectValue>
              {field === 'all' ? 'All fields' : FIELD_LABELS[field] ?? field}
            </SelectValue>
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All fields</SelectItem>
            {Object.values(AppChangesField).map((value) => (
              <SelectItem key={value} value={value}>
                {FIELD_LABELS[value] ?? value}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="inline-flex items-center rounded-lg border bg-background p-0.5">
          <PlatformTab active={platform === 'all'} onClick={() => setPlatform('all')}>
            All
          </PlatformTab>
          <PlatformTab active={platform === 'ios'} onClick={() => setPlatform('ios')}>
            <AppStoreSvg className="h-4 w-4" />
            App Store
          </PlatformTab>
          <PlatformTab active={platform === 'android'} onClick={() => setPlatform('android')}>
            <GooglePlaySvg className="h-4 w-4" />
            Google Play
          </PlatformTab>
        </div>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-20">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>
      ) : changes.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 py-20 text-center">
          <p className="text-lg font-medium">No changes detected yet</p>
          <p className="text-sm text-muted-foreground">Changes will appear here when tracked apps update their store listings</p>
        </div>
      ) : (
        <div className="space-y-3">
          {changes.map((change) => (
            <ChangeCard
              key={change.id}
              appName={change.app?.name}
              appPlatform={change.app?.platform}
              appExternalId={change.app?.external_id}
              iconUrl={change.app?.icon_url}
              beforeVersion={change.previous_version}
              afterVersion={change.version}
              locale={change.locale ?? ''}
              fieldChanged={change.field_changed ?? ''}
              oldValue={change.old_value ?? null}
              newValue={change.new_value ?? null}
              detectedAt={change.detected_at ?? ''}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function PlatformTab({
  active,
  onClick,
  children,
}: {
  active: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`inline-flex cursor-pointer items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
        active
          ? 'bg-accent text-accent-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground'
      }`}
    >
      {children}
    </button>
  )
}
