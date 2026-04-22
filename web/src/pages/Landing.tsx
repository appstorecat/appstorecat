import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { Copy, Check, ChevronDown, ArrowRight, Rocket } from 'lucide-react'
import { useEffect, useRef, useState, type ReactNode } from 'react'

const GITHUB_URL = 'https://github.com/appstorecat/appstorecat'
const DASHBOARD_URL = '/discovery/trending'

function DashboardOrCloudCta() {
  const token = useAuthStore((s) => s.token)
  if (token) {
    return (
      <Link
        to={DASHBOARD_URL}
        className="inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black font-semibold text-sm h-11 px-5 transition-colors whitespace-nowrap"
      >
        <Rocket className="h-4 w-4" />
        Dashboard
      </Link>
    )
  }
  return (
    <Link
      to="/register"
      className="inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black font-semibold text-sm h-11 px-5 transition-colors whitespace-nowrap"
    >
      <Rocket className="h-4 w-4" />
      Open Cloud Version
      <span className="text-[10px] font-bold bg-black/20 px-1.5 py-0.5 rounded">DEMO</span>
    </Link>
  )
}

function useInstallCommand() {
  const [origin, setOrigin] = useState('https://appstore.cat')
  useEffect(() => {
    if (typeof window !== 'undefined') setOrigin(window.location.origin)
  }, [])
  return `curl -sSL ${origin}/install.sh | sh`
}

function GithubIcon({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden className={className}>
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0 1 12 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.338 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.02 10.02 0 0 0 22 12.017C22 6.484 17.522 2 12 2Z"
      />
    </svg>
  )
}

function Logo({ className = 'h-7 w-7' }: { className?: string }) {
  return <img src="/appstorecat-icon.svg" alt="" className={className} />
}

function InstallButton() {
  const cmd = useInstallCommand()
  const [copied, setCopied] = useState(false)
  const onCopy = async () => {
    await navigator.clipboard.writeText(cmd)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }
  return (
    <button
      onClick={onCopy}
      aria-label="Copy install command"
      className="group relative flex w-full sm:w-auto sm:max-w-full items-center gap-3 border border-[#262626] bg-[#0a0a0a] hover:bg-[#151515] px-4 h-11 font-mono text-sm text-white/90 transition-colors overflow-hidden"
    >
      <span className="text-emerald-400 shrink-0">$</span>
      <span className="select-all truncate">{cmd}</span>
      {copied ? (
        <Check className="h-4 w-4 text-emerald-400 shrink-0" />
      ) : (
        <Copy className="h-4 w-4 text-white/40 group-hover:text-white/70 shrink-0" />
      )}
    </button>
  )
}

function SecondaryLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center justify-center gap-2 border border-[#262626] bg-[#0a0a0a] hover:bg-[#151515] text-white font-semibold text-sm h-11 px-5 transition-colors whitespace-nowrap"
    >
      {children}
    </a>
  )
}

function ProductHuntBadge() {
  return (
    <a
      href="https://www.producthunt.com/products/appstorecat?embed=true&utm_source=badge-featured&utm_medium=badge&utm_campaign=badge-appstorecat"
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex items-center shrink-0"
    >
      <img
        src="https://api.producthunt.com/widgets/embed-image/v1/featured.svg?post_id=1128947&theme=light&t=1776849718522"
        alt="AppStoreCat - Open-source app intelligence for iOS & Android. | Product Hunt"
        width={250}
        height={54}
        loading="lazy"
        className="block h-[54px] w-[250px]"
      />
    </a>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// HEADER
// ────────────────────────────────────────────────────────────────────────────

function Header() {
  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-lg border-b border-[#262626]">
      <div className="container mx-auto px-6">
        <nav className="flex items-center justify-between h-16">
          <Link to="/" className="flex items-center gap-2">
            <Logo className="h-7 w-7" />
            <span className="text-base font-semibold tracking-tight text-white">appstorecat</span>
          </Link>
          <div className="hidden md:flex items-center gap-8 text-sm text-white/70">
            <a href="#features" className="hover:text-white transition-colors">Features</a>
            <a href="#pricing" className="hover:text-white transition-colors">Pricing</a>
            <a href="#faq" className="hover:text-white transition-colors">FAQ</a>
            <a
              href={GITHUB_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 hover:text-white transition-colors"
            >
              <GithubIcon className="h-4 w-4" />
              GitHub
            </a>
          </div>
          <a
            href={GITHUB_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-sm text-white/70 hover:text-white transition-colors md:hidden"
          >
            <GithubIcon className="h-5 w-5" />
          </a>
        </nav>
      </div>
    </header>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// HERO
// ────────────────────────────────────────────────────────────────────────────

function EtherealBackground() {
  return (
    <div className="absolute inset-0 z-0 opacity-40 pointer-events-none overflow-hidden">
      <div
        className="absolute -inset-20"
        style={{
          background:
            'radial-gradient(60% 50% at 30% 30%, rgba(16,185,129,0.55) 0%, rgba(5,46,22,0.25) 45%, transparent 75%), radial-gradient(40% 40% at 80% 70%, rgba(16,185,129,0.4) 0%, transparent 70%)',
          filter: 'blur(60px)',
        }}
      />
      <div
        className="absolute inset-0 opacity-[0.08] mix-blend-overlay"
        style={{
          backgroundImage:
            "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)' opacity='0.9'/></svg>\")",
          backgroundSize: '240px',
          backgroundRepeat: 'repeat',
        }}
      />
    </div>
  )
}

function Hero() {
  return (
    <section className="pt-24 md:pt-32 pb-16 md:pb-20 relative overflow-hidden border-b border-[#262626]">
      <EtherealBackground />
      <div className="container mx-auto px-6 relative z-10">
        <div className="grid lg:grid-cols-2 gap-8 md:gap-12 lg:gap-16 items-center">
          {/* Left: content */}
          <div>
            <div className="flex items-center gap-3 mb-6 md:mb-8">
              <span className="inline-flex items-center gap-2 px-3 py-1 text-xs font-medium bg-[#171717] text-white/60 border border-[#262626]">
                Now tracking App Store & Play Store
                <a
                  href="#features"
                  className="text-white hover:underline inline-flex items-center gap-1"
                >
                  Learn more
                  <ArrowRight className="h-3 w-3" />
                </a>
              </span>
            </div>
            <h1 className="text-3xl sm:text-4xl md:text-5xl xl:text-6xl font-semibold text-white leading-tight text-balance tracking-tight">
              App Store intelligence.
              <br />
              <span className="bg-gradient-to-r from-emerald-400 to-emerald-300 bg-clip-text text-transparent">
                Open source, self-hosted.
              </span>
            </h1>
            <p className="mt-4 md:mt-6 text-base md:text-lg lg:text-xl text-white/60">
              Track competitors on the App Store and Play Store, monitor listing changes, analyze
              reviews, and discover trending apps. Free and open source. Your data, your servers.
            </p>
            <div className="mt-6 md:mt-10 flex flex-col items-stretch gap-3 sm:gap-4">
              <div className="max-w-full">
                <InstallButton />
              </div>
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 sm:gap-4">
                <SecondaryLink href={GITHUB_URL}>
                  <GithubIcon className="h-4 w-4" />
                  View on GitHub
                </SecondaryLink>
                <DashboardOrCloudCta />
                <div className="sm:ml-auto">
                  <ProductHuntBadge />
                </div>
              </div>
            </div>
          </div>
          {/* Right: live terminal */}
          <div className="relative">
            <HeroLiveTerminal />
          </div>
        </div>
      </div>
    </section>
  )
}

function BrowserFrame({
  src,
  alt,
  label,
}: {
  src: string
  alt: string
  label?: string
}) {
  return (
    <div className="border border-[#262626] bg-[#0a0a0a] overflow-hidden shadow-2xl shadow-emerald-500/10">
      <div className="flex items-center gap-2 px-4 py-3 bg-[#171717] border-b border-[#262626]">
        <div className="flex gap-1.5">
          <div className="w-3 h-3 bg-red-500/80 rounded-full" />
          <div className="w-3 h-3 bg-yellow-500/80 rounded-full" />
          <div className="w-3 h-3 bg-emerald-500/80 rounded-full" />
        </div>
        {label && (
          <span className="ml-3 inline-flex items-center gap-2 text-xs text-white/40 font-mono">
            {label}
          </span>
        )}
      </div>
      <img src={src} alt={alt} loading="lazy" className="block w-full h-auto" />
    </div>
  )
}

type TerminalLine = { delay: number; render: ReactNode }

type Stage =
  | { kind: 'terminal-line'; render: ReactNode; delay: number }
  | { kind: 'browser-preview'; src: string; alt: string; url: string; delay: number }

function HeroLiveTerminal() {
  const stages: Stage[] = [
    {
      kind: 'terminal-line',
      delay: 0,
      render: (
        <p>
          <span className="text-emerald-400">$</span>{' '}
          <span className="text-white">curl -sSL appstore.cat/install.sh | sh</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 600,
      render: (
        <p className="text-white/60">Open-source App Store &amp; Play Store intelligence</p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 1400,
      render: (
        <p className="text-white/80">
          [1/3] <span className="text-white/60">Cloning repository…</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 2200,
      render: (
        <p className="text-white/80">
          [2/3] <span className="text-white/60">Building and setting up…</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 3000,
      render: (
        <p className="text-white/80">
          [3/3] <span className="text-white/60">Starting all services…</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 3800,
      render: (
        <div className="space-y-0.5">
          <p className="text-emerald-400">✓ AppStoreCat is running!</p>
          <p className="text-white/70">
            Open your browser:{' '}
            <span className="text-white">https://{'{your_domain}'}.com</span>
          </p>
        </div>
      ),
    },
    {
      kind: 'browser-preview',
      delay: 4600,
      src: '/screenshots/hero-dashboard.jpeg',
      alt: 'AppStoreCat dashboard — trending App Store and Play Store',
      url: '{your_domain}.com/discovery/trending',
    },
    {
      kind: 'terminal-line',
      delay: 5000,
      render: (
        <p className="mt-3 text-white/70">
          Add to Claude Code for AI queries:
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 5400,
      render: (
        <p>
          <span className="text-emerald-400">$</span>{' '}
          <span className="text-white">claude mcp add appstorecat \</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 5800,
      render: (
        <p className="pl-4">
          <span className="text-white">-e APPSTORECAT_API_URL="http://localhost:7460/api/v1" \</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 6200,
      render: (
        <p className="pl-4">
          <span className="text-white">-e APPSTORECAT_API_TOKEN="..." \</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 6600,
      render: (
        <p className="pl-4">
          <span className="text-white">-- npx -y @appstorecat/mcp</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 7200,
      render: <p className="text-emerald-400">✓ MCP server connected</p>,
    },
    {
      kind: 'terminal-line',
      delay: 8000,
      render: (
        <p className="mt-3 text-white/80">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">What are the top 3 trending free iOS apps in the US?</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 8800,
      render: (
        <div className="pl-3 border-l border-emerald-500/30 text-white/70 space-y-0.5">
          <p>1. TurboTax — Finance</p>
          <p>2. ChatGPT — Productivity</p>
          <p>3. Claude by Anthropic — Productivity</p>
        </div>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 10000,
      render: (
        <p className="mt-2 text-white/80">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">What changed on ChatGPT's listing this week?</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 10800,
      render: (
        <div className="pl-3 border-l border-emerald-500/30 text-white/70 space-y-0.5">
          <p>Subtitle updated 2 days ago:</p>
          <p className="text-red-400">− Your AI assistant for everyday questions</p>
          <p className="text-emerald-400">+ Chat, voice, and image in one place</p>
        </div>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 12000,
      render: (
        <p className="mt-2 text-white/80">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">Who are Instagram's direct competitors on Google Play?</span>
        </p>
      ),
    },
    {
      kind: 'terminal-line',
      delay: 12800,
      render: (
        <div className="pl-3 border-l border-emerald-500/30 text-white/70 space-y-0.5">
          <p>TikTok · Snapchat · Threads · BeReal · Pinterest</p>
        </div>
      ),
    },
  ]

  const [visibleCount, setVisibleCount] = useState(0)

  useEffect(() => {
    const timers = stages.map((s, i) =>
      setTimeout(() => setVisibleCount((c) => Math.max(c, i + 1)), s.delay),
    )
    return () => timers.forEach((t) => clearTimeout(t))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const scrollRef = useRef<HTMLDivElement>(null)
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [visibleCount])

  return (
    <div className="border border-[#262626] bg-[#0a0a0a] overflow-hidden shadow-2xl shadow-emerald-500/10">
      <div className="flex items-center gap-2 px-4 py-3 bg-[#171717] border-b border-[#262626]">
        <div className="flex gap-1.5">
          <div className="w-3 h-3 bg-red-500/80 rounded-full" />
          <div className="w-3 h-3 bg-yellow-500/80 rounded-full" />
          <div className="w-3 h-3 bg-emerald-500/80 rounded-full" />
        </div>
        <span className="ml-3 text-xs text-white/40 font-mono">Terminal — appstorecat</span>
      </div>
      <div
        ref={scrollRef}
        className="p-4 font-mono text-xs md:text-sm h-[480px] md:h-[600px] overflow-y-auto space-y-1.5"
      >
        {stages.slice(0, visibleCount).map((s, i) => (
          <div key={i} className="animate-terminal-line">
            {s.kind === 'terminal-line' ? (
              s.render
            ) : (
              <InlineBrowserPreview src={s.src} alt={s.alt} url={s.url} />
            )}
          </div>
        ))}
        <span className="inline-block h-4 w-1.5 bg-emerald-400 align-middle animate-pulse" />
      </div>
    </div>
  )
}

function InlineBrowserPreview({ src, alt, url }: { src: string; alt: string; url: string }) {
  return (
    <div className="my-3 border border-[#262626] bg-[#0a0a0a] overflow-hidden">
      <div className="flex items-center gap-2 px-3 py-2 bg-[#151515] border-b border-[#262626]">
        <div className="flex gap-1">
          <div className="w-2 h-2 rounded-full bg-red-500/70" />
          <div className="w-2 h-2 rounded-full bg-yellow-500/70" />
          <div className="w-2 h-2 rounded-full bg-emerald-500/70" />
        </div>
        <div className="ml-2 flex-1 rounded-sm bg-black/40 px-2 py-0.5 text-[10px] text-white/50 truncate">
          {url}
        </div>
      </div>
      <img src={src} alt={alt} loading="lazy" className="block w-full h-auto" />
    </div>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FEATURES
// ────────────────────────────────────────────────────────────────────────────

type Feature = {
  eyebrow: string
  title: string
  copy: string
  commands: string[]
  image: string
  imageAlt: string
  imageLabel: string
}

function FeatureRow({ feature, flip }: { feature: Feature; flip: boolean }) {
  const TextCell = (
    <div
      className={`p-6 md:p-10 lg:p-12 flex flex-col justify-center border-b border-[#262626] ${
        flip ? 'lg:col-start-2' : 'lg:border-r'
      }`}
    >
      <div className="flex items-center gap-3 mb-4">
        <span className="text-xs font-medium text-emerald-400 uppercase tracking-wider">
          {feature.eyebrow}
        </span>
        <span className="h-px flex-1 bg-[#262626] max-w-[100px]" />
      </div>
      <h3 className="text-xl sm:text-2xl md:text-3xl font-semibold text-white tracking-tight mb-3 md:mb-4">
        {feature.title}
      </h3>
      <p className="text-sm md:text-base text-white/60 mb-4 md:mb-6 leading-relaxed">
        {feature.copy}
      </p>
      <div className="flex flex-wrap gap-2">
        {feature.commands.map((c) => (
          <code
            key={c}
            className="text-xs px-2 py-1 bg-[#171717] text-white/70 font-mono border border-[#262626]"
          >
            {c}
          </code>
        ))}
      </div>
    </div>
  )

  const TerminalCell = (
    <div
      className={`relative border-b border-[#262626] ${flip ? 'lg:col-start-1 lg:border-r' : ''}`}
    >
      <div
        className="absolute inset-0"
        style={{
          background:
            'radial-gradient(80% 60% at 30% 40%, rgba(16,185,129,0.08) 0%, transparent 70%)',
        }}
      />
      <div className="relative z-10 p-6 md:p-10 lg:p-12">
        <BrowserFrame
          src={feature.image}
          alt={feature.imageAlt}
          label={feature.imageLabel}
        />
      </div>
    </div>
  )

  return (
    <div className={`grid lg:grid-cols-2 ${flip ? 'lg:grid-flow-dense' : ''}`}>
      {TextCell}
      {TerminalCell}
    </div>
  )
}

function Features() {
  const features: Feature[] = [
    {
      eyebrow: 'Discover',
      title: 'Find every app that matters',
      copy:
        'Search the App Store and Play Store in seconds. Explore trending charts by country and category, and jump into publisher catalogs to spot rising competitors.',
      commands: ['/discovery/trending', '/apps/search'],
      image: '/screenshots/app-discovery.jpeg',
      imageAlt: 'App Store and Play Store search results',
      imageLabel: 'Discover Apps',
    },
    {
      eyebrow: 'Track',
      title: 'Watch competitors without effort',
      copy:
        'Add any app to your watchlist. AppStoreCat syncs its store listing, metadata, pricing, and daily rank automatically — so you never miss what the competition ships.',
      commands: ['/apps/track', '/competitors'],
      image: '/screenshots/app-detail.jpeg',
      imageAlt: 'Tracked app detail with store listing, versions and changes',
      imageLabel: 'App Detail',
    },
    {
      eyebrow: 'Analyze',
      title: 'Understand what users really say',
      copy:
        'Dig into reviews across markets, compare ratings over time, and benchmark your app against direct competitors. Turn App Store and Play Store signals into clear decisions.',
      commands: ['/reviews', '/charts'],
      image: '/screenshots/ratings-reviews.jpeg',
      imageAlt: 'Star rating distribution and review list',
      imageLabel: 'Ratings & Reviews',
    },
    {
      eyebrow: 'Alert',
      title: 'Get notified when things change',
      copy:
        'Subtitle tweak, new screenshot, price drop, version bump — every change on a tracked listing becomes a clean event you can watch, export, or push anywhere.',
      commands: ['/changes', '/changes/apps'],
      image: '/screenshots/competitor-tracking.jpeg',
      imageAlt: 'Competitor overview with tracked apps',
      imageLabel: 'Competitor Tracking',
    },
  ]

  return (
    <section id="features" className="pt-10 md:pt-16 relative border-b border-[#262626]">
      <div className="container mx-auto px-6">
        <div className="text-center pb-10 md:pb-16 relative" id="features-header">
          <p className="text-sm font-medium text-emerald-400 mb-3 md:mb-4">Features</p>
          <h2 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold text-white tracking-tight mb-4 md:mb-6">
            Everything you need for app intelligence
          </h2>
          <p className="text-base md:text-lg text-white/60 max-w-2xl mx-auto">
            From discovery to alerts, AppStoreCat handles the full App Store and Play Store
            monitoring workflow — without vendor lock-in.
          </p>
          <div className="absolute bottom-0 left-1/2 -translate-x-1/2 w-screen h-px bg-[#262626]" />
        </div>
      </div>

      <div className="max-w-[1280px] mx-auto border-x border-[#262626] relative">
        <div className="absolute -top-px left-1/2 -translate-x-1/2 w-screen h-px bg-[#262626]" />
        {features.map((f, i) => (
          <FeatureRow key={f.eyebrow} feature={f} flip={i % 2 === 1} />
        ))}
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// PRICING / OPEN SOURCE
// ────────────────────────────────────────────────────────────────────────────

function Pricing() {
  const perks = [
    'Unlimited app tracking',
    'App Store & Play Store coverage',
    'Daily rank and chart snapshots',
    'Store listing change monitoring',
    'Review search and sentiment',
    'Publisher and catalog explorer',
    'Local-first architecture',
    'Claude Code MCP integration',
  ]
  return (
    <section id="pricing" className="pt-10 md:pt-16 pb-16 md:pb-32 bg-[#0a0a0a]/50 border-b border-[#262626]">
      <div className="container mx-auto px-6">
        <div className="text-center pb-10 md:pb-16">
          <p className="text-sm font-medium text-emerald-400 mb-3 md:mb-4">Open Source</p>
          <h2 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold text-white tracking-tight mb-4 md:mb-6">
            Free &amp; Open Source
          </h2>
          <p className="text-base md:text-lg text-white/60 max-w-2xl mx-auto">
            No pricing tiers, no subscriptions. AppStoreCat is completely free and open source.
          </p>
        </div>

        <div className="max-w-3xl mx-auto border border-[#262626] bg-[#0a0a0a]">
          <div className="px-6 md:px-10 py-8 border-b border-[#262626] text-center">
            <span className="inline-flex items-center gap-2 border border-emerald-500/40 bg-emerald-500/10 text-emerald-300 px-3 py-1 text-xs font-mono">
              $0 · forever
            </span>
            <h3 className="mt-4 text-2xl md:text-3xl font-semibold text-white">
              Everything included
            </h3>
            <p className="mt-2 text-white/60">All features, no limits, forever free.</p>
          </div>
          <ul className="grid sm:grid-cols-2 gap-0 border-b border-[#262626]">
            {perks.map((p, i) => (
              <li
                key={p}
                className={`flex items-center gap-3 px-6 md:px-8 py-4 border-[#262626] ${
                  i % 2 === 0 ? 'sm:border-r' : ''
                } ${i < perks.length - 2 ? 'border-b' : ''}`}
              >
                <Check className="h-4 w-4 text-emerald-400 flex-shrink-0" />
                <span className="text-sm text-white/80">{p}</span>
              </li>
            ))}
          </ul>
          <div className="px-6 md:px-10 py-8 text-center space-y-4">
            <p className="text-sm text-white/60">Install with one command:</p>
            <div className="flex justify-center">
              <InstallButton />
            </div>
            <div className="pt-2">
              <a
                href={GITHUB_URL}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 text-sm text-white/60 hover:text-white transition-colors"
              >
                <GithubIcon className="h-4 w-4" />
                View on GitHub
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FAQ
// ────────────────────────────────────────────────────────────────────────────

type QA = { q: string; a: string }

function FaqItem({ item, open, onToggle }: { item: QA; open: boolean; onToggle: () => void }) {
  return (
    <div className="border-b border-[#262626]">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-6 py-5 md:py-6 text-left"
      >
        <span className="text-base md:text-lg font-medium text-white">{item.q}</span>
        <ChevronDown
          className={`h-4 w-4 flex-shrink-0 text-white/50 transition-transform ${open ? 'rotate-180' : ''}`}
        />
      </button>
      {open && <p className="pb-6 pr-10 text-sm md:text-base text-white/60 leading-relaxed">{item.a}</p>}
    </div>
  )
}

function Faq() {
  const items: QA[] = [
    {
      q: 'What is AppStoreCat?',
      a: 'AppStoreCat is a free, open-source app intelligence toolkit. It tracks competitors on the Apple App Store and Google Play Store, monitors listing changes, follows trending charts, and analyzes reviews — all from a dashboard you host yourself. Install with one command and start tracking apps.',
    },
    {
      q: 'Which stores does it support?',
      a: 'Both the Apple App Store (iOS) and Google Play Store (Android). You can search, track, compare apps, and discover trending content across dozens of countries on each store.',
    },
    {
      q: 'Is my data secure?',
      a: 'Yes. AppStoreCat runs entirely on your own infrastructure. Nothing leaves your servers. The code is open source on GitHub, so you can audit every line that handles your data.',
    },
    {
      q: 'What do I need to install it?',
      a: 'Docker, git, and make. Run the one-line install script, wait a minute, and you are up. You do not need an Apple or Google developer account to track public app data.',
    },
    {
      q: 'How does change monitoring work?',
      a: 'AppStoreCat syncs every tracked app daily and records diffs — title, subtitle, description, screenshots, version, price, release notes. You get a clean timeline of what changed on the listing, not a noisy feed.',
    },
    {
      q: 'Can I use it with Claude Code?',
      a: 'Yes. AppStoreCat ships with an MCP server, so you can query your own app intelligence directly from Claude Code and other AI tools. Ask questions in natural language and get answers from your tracked data.',
    },
  ]
  const [openIdx, setOpenIdx] = useState<number | null>(0)
  return (
    <section id="faq" className="pt-10 md:pt-16 pb-16 md:pb-32 border-b border-[#262626]">
      <div className="container mx-auto px-6">
        <div className="text-center pb-10 md:pb-16">
          <p className="text-sm font-medium text-emerald-400 mb-3 md:mb-4">FAQ</p>
          <h2 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold text-white tracking-tight">
            Questions &amp; answers
          </h2>
        </div>
        <div className="max-w-3xl mx-auto border-t border-[#262626]">
          {items.map((it, i) => (
            <FaqItem
              key={it.q}
              item={it}
              open={openIdx === i}
              onToggle={() => setOpenIdx(openIdx === i ? null : i)}
            />
          ))}
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FINAL CTA
// ────────────────────────────────────────────────────────────────────────────

function FinalCta() {
  return (
    <section className="py-16 md:py-32 border-t border-[#262626] relative overflow-hidden">
      <div
        className="absolute inset-0 pointer-events-none opacity-30"
        style={{
          background:
            'radial-gradient(60% 50% at 50% 50%, rgba(16,185,129,0.25) 0%, transparent 70%)',
        }}
      />
      <div className="container mx-auto px-6 relative">
        <div className="text-center max-w-2xl mx-auto">
          <h2 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-semibold text-white tracking-tight mb-4 md:mb-6">
            Install AppStoreCat
          </h2>
          <p className="text-base md:text-lg text-white/60 mb-8 md:mb-10">
            Free and open source. Start tracking App Store and Play Store apps in minutes.
          </p>
          <div className="flex flex-wrap justify-center items-center gap-3 md:gap-4">
            <InstallButton />
            <SecondaryLink href={GITHUB_URL}>
              <GithubIcon className="h-4 w-4" />
              View on GitHub
            </SecondaryLink>
            <DashboardOrCloudCta />
          </div>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FOOTER
// ────────────────────────────────────────────────────────────────────────────

type FooterLink = { label: string; href: string; external?: boolean }

function Footer() {
  const columns: Array<{ title: string; links: FooterLink[] }> = [
    {
      title: 'Product',
      links: [
        { label: 'Features', href: '#features' },
        { label: 'Pricing', href: '#pricing' },
        { label: 'GitHub', href: GITHUB_URL, external: true },
        { label: 'Install', href: `${GITHUB_URL}#installation`, external: true },
      ],
    },
    {
      title: 'Tools',
      links: [
        { label: 'App search', href: '/apps/search' },
        { label: 'Trending', href: '/discovery/trending' },
        { label: 'Publishers', href: '/publishers' },
        { label: 'Changes', href: '/changes' },
        { label: 'Reviews', href: '/reviews' },
      ],
    },
    {
      title: 'Resources',
      links: [
        { label: 'README', href: GITHUB_URL, external: true },
        { label: 'Changelog', href: `${GITHUB_URL}/releases`, external: true },
        { label: 'Issues', href: `${GITHUB_URL}/issues`, external: true },
        { label: 'License', href: `${GITHUB_URL}/blob/master/LICENSE`, external: true },
      ],
    },
    {
      title: 'Company',
      links: [
        { label: 'Contact', href: `${GITHUB_URL}/issues/new`, external: true },
        { label: 'GitHub', href: GITHUB_URL, external: true },
      ],
    },
  ]
  return (
    <footer className="border-t border-[#262626]">
      <div className="container mx-auto px-6 py-12 md:py-16">
        <div className="grid md:grid-cols-5 gap-10">
          <div className="md:col-span-1">
            <Link to="/" className="flex items-center gap-2">
              <Logo className="h-7 w-7" />
              <span className="text-base font-semibold text-white">appstorecat</span>
            </Link>
            <p className="mt-4 text-sm text-white/50">
              Open-source app intelligence for the Apple App Store and Google Play Store.
            </p>
          </div>
          {columns.map((col) => (
            <div key={col.title}>
              <h4 className="text-xs font-semibold uppercase tracking-widest text-white/50 mb-4">
                {col.title}
              </h4>
              <ul className="space-y-2">
                {col.links.map((l) => (
                  <li key={l.label}>
                    {l.external ? (
                      <a
                        href={l.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-white/70 hover:text-white transition-colors"
                      >
                        {l.label}
                      </a>
                    ) : (
                      <Link
                        to={l.href}
                        className="text-sm text-white/70 hover:text-white transition-colors"
                      >
                        {l.label}
                      </Link>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <div className="mt-12 pt-8 border-t border-[#262626] flex flex-col sm:flex-row justify-between items-center gap-4">
          <p className="text-xs text-white/40">© 2026 AppStoreCat. Open source under MIT.</p>
          <a
            href={GITHUB_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 text-xs text-white/50 hover:text-white transition-colors"
          >
            <GithubIcon className="h-4 w-4" />
            github.com/appstorecat
          </a>
        </div>
      </div>
    </footer>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// EXPORT
// ────────────────────────────────────────────────────────────────────────────

export default function Landing() {
  return (
    <div className="min-h-screen bg-black text-white antialiased">
      <Header />
      <main className="pt-16">
        <Hero />
        <Features />
        <Pricing />
        <Faq />
        <FinalCta />
      </main>
      <Footer />
    </div>
  )
}
