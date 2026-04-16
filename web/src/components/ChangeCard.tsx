import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ChevronDown, ChevronUp, Smartphone } from 'lucide-react'

interface ChangeCardProps {
  appName?: string
  appPlatform?: string
  appExternalId?: string
  iconUrl?: string | null
  version?: string | null
  beforeVersion?: string | null
  afterVersion?: string | null
  language: string
  fieldChanged: string
  oldValue: string | null
  newValue: string | null
  detectedAt: string
  showAppInfo?: boolean
}

const fieldLabels: Record<string, string> = {
  title: 'Title',
  subtitle: 'Subtitle',
  description: 'Description',
  whats_new: "What's New",
  country_added: 'Country Added',
  country_removed: 'Country Removed',
}


function formatLanguage(language: string): string {
  try {
    const display = new Intl.DisplayNames(['en'], { type: 'region' })
    return display.of(language.toUpperCase()) ?? language
  } catch {
    return language
  }
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

export default function ChangeCard({
  appName,
  appPlatform,
  appExternalId,
  iconUrl,
  version,
  beforeVersion,
  afterVersion,
  language,
  fieldChanged,
  oldValue,
  newValue,
  detectedAt,
  showAppInfo = true,
}: ChangeCardProps) {
  const [expanded, setExpanded] = useState(false)

  const isLong =
    (oldValue && oldValue.length > 200) || (newValue && newValue.length > 200)
  const isLocaleChange =
    fieldChanged === 'country_added' || fieldChanged === 'country_removed'

  const label = fieldLabels[fieldChanged] ?? fieldChanged
  const displayLanguage = formatLanguage(language)

  return (
    <div className="rounded-lg border p-4">
      {/* Header */}
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          {showAppInfo && (
            <Link to={appPlatform && appExternalId ? `/apps/${appPlatform}/${appExternalId}` : '#'}>
              {iconUrl ? (
                <img src={iconUrl} alt="" className="h-10 w-10 shrink-0 rounded-xl" />
              ) : (
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                  <Smartphone className="h-4 w-4 text-muted-foreground" />
                </div>
              )}
            </Link>
          )}
          <div>
            {showAppInfo && appName && appPlatform && appExternalId ? (
              <Link
                to={`/apps/${appPlatform}/${appExternalId}`}
                className="text-sm font-semibold hover:underline"
              >
                {appName}
              </Link>
            ) : showAppInfo ? (
              <span className="text-sm font-semibold">Unknown App</span>
            ) : null}
            <div className="flex items-center gap-2">
              <Badge
                variant="secondary"
                className="text-[10px] font-semibold"
              >
                {label}
              </Badge>
              <span className="text-xs text-muted-foreground">{displayLanguage}</span>
            </div>
          </div>
        </div>
        <time className="shrink-0 text-xs text-muted-foreground">
          {formatDate(detectedAt)}
        </time>
      </div>

      {/* Content */}
      {isLocaleChange ? (
        <div className="mt-3">
          <div
            className={`rounded-md p-2.5 ${
              fieldChanged === 'country_added'
                ? 'bg-green-500/5'
                : 'bg-red-500/5'
            }`}
          >
            <span
              className={`text-[10px] font-semibold uppercase ${
                fieldChanged === 'country_added'
                  ? 'text-green-500'
                  : 'text-red-500'
              }`}
            >
              {fieldChanged === 'country_added'
                ? `${displayLanguage} added`
                : `${displayLanguage} removed`}
            </span>
            {(oldValue || newValue) && (
              <p className="mt-1 text-xs text-muted-foreground">
                Title: {oldValue || newValue}
              </p>
            )}
          </div>
        </div>
      ) : (
        (oldValue || newValue) && (
          <>
            <div className="mt-3 grid gap-2 text-sm md:grid-cols-2">
              <div className="rounded-md bg-red-500/5 p-2.5">
                <span className="mb-1 block text-[10px] font-semibold uppercase text-red-500">
                  Before{beforeVersion ? ` (v${beforeVersion})` : ''}
                </span>
                <p
                  className={`whitespace-pre-line text-xs text-muted-foreground ${
                    !expanded && isLong ? 'line-clamp-3' : ''
                  }`}
                >
                  {oldValue || '\u2014'}
                </p>
              </div>
              <div className="rounded-md bg-green-500/5 p-2.5">
                <span className="mb-1 block text-[10px] font-semibold uppercase text-green-500">
                  After{afterVersion ? ` (v${afterVersion})` : ''}
                </span>
                <p
                  className={`whitespace-pre-line text-xs text-muted-foreground ${
                    !expanded && isLong ? 'line-clamp-3' : ''
                  }`}
                >
                  {newValue || '\u2014'}
                </p>
              </div>
            </div>
            {isLong && (
              <Button
                variant="ghost"
                size="sm"
                className="mt-1 h-6 text-xs text-muted-foreground"
                onClick={() => setExpanded(!expanded)}
              >
                {expanded ? (
                  <>
                    <ChevronUp className="mr-1 h-3 w-3" /> Collapse
                  </>
                ) : (
                  <>
                    <ChevronDown className="mr-1 h-3 w-3" /> Show full diff
                  </>
                )}
              </Button>
            )}
          </>
        )
      )}
    </div>
  )
}
