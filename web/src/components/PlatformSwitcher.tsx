const AppStoreSvg = ({ className }: { className?: string }) => (
  <svg className={className ?? 'h-5 w-5'} viewBox="0 0 24 24" fill="currentColor">
    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11" />
  </svg>
)

const GooglePlaySvg = ({ className }: { className?: string }) => (
  <svg className={className ?? 'h-5 w-5'} viewBox="0 0 24 24" fill="currentColor">
    <path d="M3.18 23.67c-.33-.18-.55-.53-.55-.93V1.26c0-.4.22-.75.55-.93l10.7 11.67-10.7 11.67zm1.4.86l13.03-7.13-3.19-3.47L4.58 24.53zm13.03-15.27L4.58-.47l9.84 10.6 3.19-3.47zm1.97 1.08l-3.5 1.91 3.5 3.82 2.74-1.5c.56-.31.56-1.09 0-1.4l-2.74-2.83z" />
  </svg>
)

interface PlatformSwitcherProps {
  value: string
  onChange: (value: string) => void
}

export default function PlatformSwitcher({ value, onChange }: PlatformSwitcherProps) {
  return (
    <div className="flex w-full items-center rounded-lg border bg-background p-0.5 sm:inline-flex sm:w-auto">
      <button
        type="button"
        onClick={() => onChange('ios')}
        title="App Store"
        aria-label="App Store"
        className={`inline-flex flex-1 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors sm:flex-initial ${
          value === 'ios'
            ? 'bg-accent text-accent-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'
        }`}
      >
        <AppStoreSvg className="h-4 w-4" />
        <span className="hidden sm:inline">App Store</span>
      </button>
      <button
        type="button"
        onClick={() => onChange('android')}
        title="Google Play"
        aria-label="Google Play"
        className={`inline-flex flex-1 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors sm:flex-initial ${
          value === 'android'
            ? 'bg-accent text-accent-foreground shadow-sm'
            : 'text-muted-foreground hover:text-foreground'
        }`}
      >
        <GooglePlaySvg className="h-4 w-4" />
        <span className="hidden sm:inline">Google Play</span>
      </button>
    </div>
  )
}

export { AppStoreSvg, GooglePlaySvg }
