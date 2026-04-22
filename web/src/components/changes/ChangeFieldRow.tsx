import { useState } from 'react'
import { Button } from '@/components/ui/button'
import { ChevronDown, ChevronUp } from 'lucide-react'

export interface ChangeFieldRowProps {
  field: string
  locale: string
  oldValue: string | null
  newValue: string | null
  beforeVersion?: string | null
  afterVersion?: string | null
}

const FIELD_LABELS: Record<string, string> = {
  title: 'Title',
  subtitle: 'Subtitle',
  description: 'Description',
  whats_new: "What's New",
  screenshots: 'Screenshots',
  locale_added: 'Locale Added',
  locale_removed: 'Locale Removed',
}

function formatLocale(locale: string): string {
  try {
    const display = new Intl.DisplayNames(['en'], { type: 'region' })
    return display.of(locale.toUpperCase()) ?? locale
  } catch {
    return locale
  }
}

export default function ChangeFieldRow({
  field,
  locale,
  oldValue,
  newValue,
  beforeVersion,
  afterVersion,
}: ChangeFieldRowProps) {
  const [expanded, setExpanded] = useState(false)
  const label = FIELD_LABELS[field] ?? field
  const displayLocale = formatLocale(locale)

  const isLocaleChange = field === 'locale_added' || field === 'locale_removed'
  const isScreenshots = field === 'screenshots'
  const isLong =
    (oldValue?.length ?? 0) > 200 || (newValue?.length ?? 0) > 200

  return (
    <div className="rounded-md border bg-background/40 p-3">
      <div className="mb-2 flex items-center gap-2 text-xs">
        <span className="font-medium text-foreground">{label}</span>
        <span className="text-muted-foreground">· {displayLocale}</span>
        <span className="text-muted-foreground/60">· {locale}</span>
      </div>

      {isScreenshots ? (
        <p className="text-xs text-muted-foreground">
          Screenshots changed. Open the app detail to compare.
        </p>
      ) : isLocaleChange ? (
        <div
          className={`rounded-sm px-2 py-1.5 text-xs ${
            field === 'locale_added'
              ? 'bg-emerald-500/10 text-emerald-400'
              : 'bg-red-500/10 text-red-400'
          }`}
        >
          {field === 'locale_added' ? (
            <>
              <span className="font-medium">{displayLocale}</span> added
              {newValue ? <span className="text-muted-foreground"> · title: {newValue}</span> : null}
            </>
          ) : (
            <>
              <span className="font-medium">{displayLocale}</span> removed
              {oldValue ? <span className="text-muted-foreground"> · title: {oldValue}</span> : null}
            </>
          )}
        </div>
      ) : (
        <div className="grid gap-2 md:grid-cols-2">
          <DiffBlock
            tone="before"
            version={beforeVersion}
            value={oldValue}
            expanded={expanded}
            isLong={isLong}
          />
          <DiffBlock
            tone="after"
            version={afterVersion}
            value={newValue}
            expanded={expanded}
            isLong={isLong}
          />
        </div>
      )}

      {!isLocaleChange && !isScreenshots && isLong && (
        <Button
          variant="ghost"
          size="sm"
          className="mt-1 h-6 px-1 text-[11px] text-muted-foreground"
          onClick={() => setExpanded((e) => !e)}
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
    </div>
  )
}

function DiffBlock({
  tone,
  version,
  value,
  expanded,
  isLong,
}: {
  tone: 'before' | 'after'
  version?: string | null
  value: string | null
  expanded: boolean
  isLong: boolean
}) {
  const bg = tone === 'before' ? 'bg-red-500/10' : 'bg-emerald-500/10'
  const color = tone === 'before' ? 'text-red-400' : 'text-emerald-400'
  const heading = tone === 'before' ? 'Before' : 'After'

  return (
    <div className={`rounded-sm p-2 ${bg}`}>
      <span className={`mb-1 block text-[10px] font-semibold uppercase tracking-wide ${color}`}>
        {heading}
        {version ? ` (v${version})` : ''}
      </span>
      <p
        dir="auto"
        className={`whitespace-pre-line text-xs text-foreground/80 ${
          !expanded && isLong ? 'line-clamp-3' : ''
        }`}
      >
        {value ?? '—'}
      </p>
    </div>
  )
}
