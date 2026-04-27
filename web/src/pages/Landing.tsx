import { Link } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import {
  Copy,
  Check,
  ChevronDown,
  ArrowUpRight,
  Star,
  Terminal,
  Sparkles,
  Search,
  LineChart,
  Bell,
  Eye,
  Globe,
  Lock,
  Boxes,
  Zap,
} from 'lucide-react'
import { useEffect, useRef, useState, type ReactNode } from 'react'

const GITHUB_URL = 'https://github.com/appstorecat/appstorecat'
const NPM_MCP_URL = 'https://www.npmjs.com/package/@appstorecat/mcp'
// Trailing slash matches Starlight's default — avoids a 301 round-trip on every doc link.
const DOCS_URL = 'https://appstorecat.github.io/appstorecat/'
const MCP_DOCS_URL = `${DOCS_URL}services/mcp/`
const INSTALL_DOCS_URL = `${DOCS_URL}getting-started/install-script/`
const DASHBOARD_URL = '/discovery/trending'

// ────────────────────────────────────────────────────────────────────────────
// PRIMITIVES
// ────────────────────────────────────────────────────────────────────────────

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

function NpmIcon({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden className={className}>
      <path d="M0 7.334v8h6.666v1.332H12v-1.332h12v-8H0zm6.666 6.664H5.334v-4H3.999v4H1.335V8.667h5.331v5.331zm4 0v1.336H8.001V8.667h5.334v5.332h-2.669v-.001zm12.001 0h-1.33v-4h-1.336v4h-1.335v-4h-1.33v4h-2.671V8.667h8.002v5.331zM10.665 10H12v2.667h-1.335V10z" />
    </svg>
  )
}

function Logo({ className = 'h-7 w-7' }: { className?: string }) {
  return <img src="/appstorecat-icon.png" alt="" className={className} />
}

function useInstallCommand() {
  const [origin] = useState(() =>
    typeof window !== 'undefined' ? window.location.origin : 'https://appstore.cat',
  )
  return `curl -sSL ${origin}/install.sh | sh`
}

function CopyableCommand({
  cmd,
  className = '',
  prefix = '$',
}: {
  cmd: string
  className?: string
  prefix?: string
}) {
  const [copied, setCopied] = useState(false)
  const onCopy = async () => {
    try {
      await navigator.clipboard.writeText(cmd)
      setCopied(true)
      setTimeout(() => setCopied(false), 1600)
    } catch {
      /* clipboard blocked */
    }
  }
  return (
    <button
      onClick={onCopy}
      aria-label="Copy command"
      className={`group flex w-full items-center gap-3 rounded-md border border-white/10 bg-white/[0.02] px-4 py-3 font-mono text-[13px] text-white/90 transition-all hover:border-emerald-500/40 hover:bg-white/[0.04] ${className}`}
    >
      <span className="text-emerald-400 shrink-0">{prefix}</span>
      <span className="select-all truncate text-left flex-1">{cmd}</span>
      {copied ? (
        <span className="flex items-center gap-1.5 text-emerald-400 shrink-0">
          <Check className="h-3.5 w-3.5" />
          <span className="text-xs">copied</span>
        </span>
      ) : (
        <Copy className="h-3.5 w-3.5 text-white/30 group-hover:text-white/70 shrink-0 transition-colors" />
      )}
    </button>
  )
}

function PrimaryButton({
  href,
  to,
  children,
  className = '',
}: {
  href?: string
  to?: string
  children: ReactNode
  className?: string
}) {
  const cls = `inline-flex items-center justify-center gap-2 rounded-md bg-emerald-500 hover:bg-emerald-400 text-black font-semibold text-sm h-11 px-5 transition-colors whitespace-nowrap ${className}`
  if (to) {
    return (
      <Link to={to} className={cls}>
        {children}
      </Link>
    )
  }
  return (
    <a href={href} target="_blank" rel="noopener noreferrer" className={cls}>
      {children}
    </a>
  )
}

function GhostButton({
  href,
  to,
  children,
  className = '',
}: {
  href?: string
  to?: string
  children: ReactNode
  className?: string
}) {
  const cls = `inline-flex items-center justify-center gap-2 rounded-md border border-white/10 bg-white/[0.02] hover:border-white/20 hover:bg-white/[0.05] text-white font-medium text-sm h-11 px-5 transition-colors whitespace-nowrap ${className}`
  if (to) {
    return (
      <Link to={to} className={cls}>
        {children}
      </Link>
    )
  }
  return (
    <a href={href} target={href?.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer" className={cls}>
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
        width={220}
        height={48}
        loading="lazy"
        className="block h-[48px] w-[220px]"
      />
    </a>
  )
}

function PrimaryCta() {
  const token = useAuthStore((s) => s.token)
  if (token) {
    return (
      <PrimaryButton to={DASHBOARD_URL}>
        <Sparkles className="h-4 w-4" />
        Open Dashboard
      </PrimaryButton>
    )
  }
  return (
    <PrimaryButton to="/register">
      <Sparkles className="h-4 w-4" />
      Try Live Demo
      <span className="text-[10px] font-bold bg-black/20 px-1.5 py-0.5 rounded">FREE</span>
    </PrimaryButton>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// HEADER
// ────────────────────────────────────────────────────────────────────────────

function HeaderAuthCta() {
  const token = useAuthStore((s) => s.token)
  if (token) {
    return (
      <Link
        to={DASHBOARD_URL}
        className="inline-flex items-center gap-1.5 px-3 h-9 rounded-md bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-semibold transition-colors"
      >
        <Sparkles className="h-3.5 w-3.5" />
        Dashboard
      </Link>
    )
  }
  return (
    <>
      <Link
        to="/login"
        className="hidden sm:inline-flex items-center px-3 h-9 text-sm text-white/70 hover:text-white transition-colors"
      >
        Login
      </Link>
      <Link
        to="/register"
        className="inline-flex items-center gap-1.5 px-3 h-9 rounded-md bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-semibold transition-colors"
      >
        <Sparkles className="h-3.5 w-3.5" />
        <span>Try Demo</span>
      </Link>
    </>
  )
}

function Header() {
  return (
    <header className="fixed top-0 left-0 right-0 z-50 bg-black/70 backdrop-blur-xl border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-7xl">
        <nav className="flex items-center justify-between h-16">
          <Link to="/" className="flex items-center gap-2.5">
            <Logo className="h-7 w-7" />
            <span className="text-base font-semibold tracking-tight text-white">appstorecat</span>
            <span className="hidden sm:inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-mono uppercase tracking-wider text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded">
              v{__APP_VERSION__}
            </span>
          </Link>
          <div className="hidden md:flex items-center gap-7 text-sm text-white/60">
            <a href="#why" className="hover:text-white transition-colors">Why</a>
            <a href="#features" className="hover:text-white transition-colors">Features</a>
            <a href="#mcp" className="hover:text-white transition-colors">MCP</a>
            <a href="#install" className="hover:text-white transition-colors">Install</a>
            <a href={DOCS_URL} target="_blank" rel="noopener noreferrer" className="hover:text-white transition-colors">
              Docs
            </a>
          </div>
          <div className="flex items-center gap-1.5 sm:gap-2">
            <a
              href={GITHUB_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 px-3 h-9 rounded-md border border-white/10 bg-white/[0.02] text-sm text-white/80 hover:border-white/20 hover:bg-white/[0.05] transition-colors"
            >
              <GithubIcon className="h-3.5 w-3.5" />
              <Star className="h-3.5 w-3.5 text-yellow-400 fill-yellow-400" />
              <span className="font-medium hidden xs:inline">Star</span>
            </a>
            <HeaderAuthCta />
          </div>
        </nav>
      </div>
    </header>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// HERO
// ────────────────────────────────────────────────────────────────────────────

function HeroBackground() {
  return (
    <div aria-hidden className="absolute inset-0 z-0 pointer-events-none overflow-hidden">
      {/* radial green glow */}
      <div
        className="absolute -top-40 left-1/2 -translate-x-1/2 w-[1200px] h-[600px] opacity-50"
        style={{
          background:
            'radial-gradient(50% 50% at 50% 50%, rgba(16,185,129,0.35) 0%, rgba(16,185,129,0.08) 40%, transparent 70%)',
          filter: 'blur(40px)',
        }}
      />
      {/* dotted grid */}
      <div
        className="absolute inset-0 opacity-[0.18]"
        style={{
          backgroundImage:
            'radial-gradient(circle at 1px 1px, rgba(255,255,255,0.4) 1px, transparent 0)',
          backgroundSize: '32px 32px',
          maskImage:
            'radial-gradient(ellipse 80% 60% at 50% 30%, black 30%, transparent 80%)',
        }}
      />
    </div>
  )
}

type HeroTab = 'self-host' | 'mcp'

function HeroInstallTabs() {
  const [tab, setTab] = useState<HeroTab>('self-host')
  const installCmd = useInstallCommand()
  const mcpCmd = `claude mcp add appstorecat \\\n  -e APPSTORECAT_API_URL=http://localhost:7460/api/v1 \\\n  -e APPSTORECAT_API_TOKEN=<your-token> \\\n  -- npx -y @appstorecat/mcp`
  const mcpCmdOneLine =
    'claude mcp add appstorecat -e APPSTORECAT_API_URL=http://localhost:7460/api/v1 -e APPSTORECAT_API_TOKEN=<your-token> -- npx -y @appstorecat/mcp'
  const [copied, setCopied] = useState(false)
  const onCopyMcp = async () => {
    try {
      await navigator.clipboard.writeText(mcpCmdOneLine)
      setCopied(true)
      setTimeout(() => setCopied(false), 1600)
    } catch {
      /* clipboard blocked */
    }
  }
  return (
    <div className="rounded-xl border border-white/10 bg-[#0a0a0a]/80 backdrop-blur overflow-hidden shadow-2xl shadow-emerald-500/5">
      <div role="tablist" className="flex items-stretch border-b border-white/10 bg-white/[0.02]">
        <button
          role="tab"
          aria-selected={tab === 'self-host'}
          onClick={() => setTab('self-host')}
          className={`flex-1 flex items-center justify-center gap-2 px-3 sm:px-5 py-3 text-xs sm:text-sm font-medium transition-colors relative ${
            tab === 'self-host'
              ? 'text-white bg-white/[0.03]'
              : 'text-white/50 hover:text-white/80'
          }`}
        >
          <Boxes className={`h-3.5 w-3.5 ${tab === 'self-host' ? 'text-emerald-400' : ''}`} />
          <span>Self-host (60s)</span>
          {tab === 'self-host' && (
            <span className="absolute inset-x-0 -bottom-px h-0.5 bg-emerald-400" />
          )}
        </button>
        <div className="w-px bg-white/10" />
        <button
          role="tab"
          aria-selected={tab === 'mcp'}
          onClick={() => setTab('mcp')}
          className={`flex-1 flex items-center justify-center gap-2 px-3 sm:px-5 py-3 text-xs sm:text-sm font-medium transition-colors relative ${
            tab === 'mcp'
              ? 'text-white bg-white/[0.03]'
              : 'text-white/50 hover:text-white/80'
          }`}
        >
          <Sparkles className={`h-3.5 w-3.5 ${tab === 'mcp' ? 'text-emerald-400' : ''}`} />
          <span>Claude Code (MCP)</span>
          {tab === 'mcp' && (
            <span className="absolute inset-x-0 -bottom-px h-0.5 bg-emerald-400" />
          )}
        </button>
      </div>

      {tab === 'self-host' ? (
        <div className="p-5 md:p-6 space-y-4 text-left">
          <p className="text-xs sm:text-sm text-white/55">
            One command. Four Docker services. Your own server, MIT-licensed forever.
          </p>
          <CopyableCommand cmd={installCmd} />
          <div className="flex items-center justify-between text-[11px] sm:text-xs text-white/40">
            <span>Requires Docker, git, make, curl</span>
            <a
              href={INSTALL_DOCS_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="text-white/60 hover:text-emerald-400 inline-flex items-center gap-0.5 transition-colors"
            >
              What does this do?
              <ArrowUpRight className="h-3 w-3" />
            </a>
          </div>
        </div>
      ) : (
        <div className="p-5 md:p-6 space-y-4 text-left">
          <p className="text-xs sm:text-sm text-white/55">
            Give Claude Code direct access. 32 tools (28 read · 4 write), Swagger-strict, chain-first.
          </p>
          <button
            onClick={onCopyMcp}
            aria-label="Copy MCP install command"
            className="group w-full flex items-start gap-3 rounded-md border border-white/10 bg-white/[0.02] px-4 py-3 font-mono text-[12px] sm:text-[13px] text-white/90 transition-all hover:border-emerald-500/40 hover:bg-white/[0.04]"
          >
            <span className="text-emerald-400 shrink-0 mt-0.5">$</span>
            <pre className="select-all flex-1 text-left whitespace-pre overflow-x-auto leading-relaxed">{mcpCmd}</pre>
            {copied ? (
              <span className="flex items-center gap-1.5 text-emerald-400 shrink-0 mt-0.5">
                <Check className="h-3.5 w-3.5" />
                <span className="text-xs">copied</span>
              </span>
            ) : (
              <Copy className="h-3.5 w-3.5 text-white/30 group-hover:text-white/70 shrink-0 mt-0.5 transition-colors" />
            )}
          </button>
          <div className="flex items-center justify-between text-[11px] sm:text-xs text-white/40">
            <span>Needs a running AppStoreCat instance + API token</span>
            <a
              href={MCP_DOCS_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="text-white/60 hover:text-emerald-400 inline-flex items-center gap-0.5 transition-colors"
            >
              MCP docs
              <ArrowUpRight className="h-3 w-3" />
            </a>
          </div>
        </div>
      )}
    </div>
  )
}

function Hero() {
  return (
    <section className="relative pt-20 md:pt-24 pb-10 md:pb-14 overflow-hidden border-b border-white/5">
      <HeroBackground />
      <div className="container mx-auto px-4 sm:px-6 max-w-5xl relative z-10">
        <div className="text-center">
          {/* eyebrow */}
          <div className="inline-flex items-center gap-2 px-3 py-1 mb-5 text-xs font-medium bg-white/[0.04] text-white/70 border border-white/10 rounded-full">
            <span className="relative flex h-1.5 w-1.5">
              <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75 animate-ping" />
              <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500" />
            </span>
            <span>v{__APP_VERSION__} · 32-tool MCP server is live</span>
            <a href="#mcp" className="text-emerald-400 hover:underline inline-flex items-center gap-0.5">
              See how
              <ArrowUpRight className="h-3 w-3" />
            </a>
          </div>

          {/* headline */}
          <h1 className="text-[2.25rem] sm:text-5xl md:text-6xl lg:text-[4.25rem] font-semibold text-white leading-[1.05] tracking-tight text-balance">
            App Store intelligence
            <br />
            <span className="bg-gradient-to-br from-emerald-300 via-emerald-400 to-emerald-500 bg-clip-text text-transparent">
              you can self-host.
            </span>
          </h1>

          {/* subheadline */}
          <p className="mt-4 md:mt-5 text-sm sm:text-base md:text-lg text-white/60 max-w-2xl mx-auto leading-relaxed">
            Track competitors on App Store &amp; Google Play, monitor every store-listing change,
            analyze reviews — and query everything from Claude Code.{' '}
            <span className="text-white/80">Open source. MIT. Your data, your servers.</span>
          </p>

          {/* Two-way install (the headline feature) */}
          <div className="mt-7 md:mt-8 max-w-2xl mx-auto">
            <HeroInstallTabs />
            <p className="mt-3 text-[11px] text-white/30 text-center">
              Or try the hosted demo — link in the top-right.
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// METRIC STRIP (Aider model)
// ────────────────────────────────────────────────────────────────────────────

function MetricStrip() {
  const items = [
    { value: '32', label: 'MCP tools' },
    { value: '230+', label: 'countries' },
    { value: '50', label: 'languages (ASO)' },
    { value: '60s', label: 'install time' },
    { value: 'MIT', label: 'license' },
  ]
  return (
    <section className="border-b border-white/5 bg-white/[0.015]">
      <div className="container mx-auto px-4 sm:px-6 max-w-6xl">
        <div className="grid grid-cols-2 md:grid-cols-5 divide-x divide-y md:divide-y-0 divide-white/5">
          {items.map((it) => (
            <div key={it.label} className="px-4 py-6 md:py-8 text-center">
              <div className="text-2xl md:text-3xl font-semibold text-white tracking-tight">
                {it.value}
              </div>
              <div className="mt-1 text-xs uppercase tracking-wider text-white/40">{it.label}</div>
            </div>
          ))}
        </div>
        <div className="border-t border-white/5 px-4 py-5 flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-6 text-xs text-white/40">
          <span className="uppercase tracking-wider">Featured on</span>
          <ProductHuntBadge />
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// LIVE TERMINAL DEMO (full-width below hero)
// ────────────────────────────────────────────────────────────────────────────

type Stage =
  | { kind: 'line'; render: ReactNode; delay: number }
  | { kind: 'preview'; src: string; alt: string; url: string; delay: number }

function LiveDemo() {
  const stages: Stage[] = [
    {
      kind: 'line',
      delay: 0,
      render: (
        <p>
          <span className="text-emerald-400">$</span>{' '}
          <span className="text-white">curl -sSL appstore.cat/install.sh | sh</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 600,
      render: <p className="text-white/50">Open-source App Store &amp; Play Store intelligence</p>,
    },
    {
      kind: 'line',
      delay: 1400,
      render: (
        <p className="text-white/70">
          <span className="text-emerald-400">[1/5]</span>{' '}
          <span className="text-white/60">Checking dependencies</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 2100,
      render: (
        <p className="text-white/70">
          <span className="text-emerald-400">[2/5]</span>{' '}
          <span className="text-white/60">Cloning repository</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 2800,
      render: (
        <p className="text-white/70">
          <span className="text-emerald-400">[3/5]</span>{' '}
          <span className="text-white/60">Preparing .env</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 3500,
      render: (
        <p className="text-white/70">
          <span className="text-emerald-400">[4/5]</span>{' '}
          <span className="text-white/60">Building containers, installing dependencies</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 4400,
      render: (
        <p className="text-white/70">
          <span className="text-emerald-400">[5/5]</span>{' '}
          <span className="text-white/60">Starting all services</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 5200,
      render: (
        <div className="space-y-0.5">
          <p className="text-emerald-400">✓ AppStoreCat is installed.</p>
          <p className="text-white/70">
            Open: <span className="text-white">http://localhost:7461</span>
          </p>
        </div>
      ),
    },
    {
      kind: 'preview',
      delay: 6000,
      src: '/screenshots/hero-dashboard.jpeg',
      alt: 'AppStoreCat dashboard — trending App Store and Play Store',
      url: 'localhost:7461/discovery/trending',
    },
    {
      kind: 'line',
      delay: 6500,
      render: (
        <p className="mt-4 text-white/60">
          <span className="text-white/40">#</span> Add to Claude Code for AI queries
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 6900,
      render: (
        <p>
          <span className="text-emerald-400">$</span>{' '}
          <span className="text-white">claude mcp add appstorecat -- npx -y @appstorecat/mcp</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 7600,
      render: <p className="text-emerald-400">✓ MCP server connected (32 tools)</p>,
    },
    {
      kind: 'line',
      delay: 8400,
      render: (
        <p className="mt-3 text-white/85">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">What changed on ChatGPT's App Store listing this week?</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 9200,
      render: (
        <div className="pl-3 border-l-2 border-emerald-500/40 text-white/70 space-y-0.5">
          <p className="text-white/60">Subtitle updated 2 days ago:</p>
          <p className="text-red-400">− Your AI assistant for everyday questions</p>
          <p className="text-emerald-400">+ Chat, voice, and image in one place</p>
        </div>
      ),
    },
    {
      kind: 'line',
      delay: 10400,
      render: (
        <p className="mt-3 text-white/85">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">Top 3 trending free iOS apps in the US?</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 11200,
      render: (
        <div className="pl-3 border-l-2 border-emerald-500/40 text-white/70 space-y-0.5">
          <p>1. TurboTax — Finance</p>
          <p>2. ChatGPT — Productivity</p>
          <p>3. Claude by Anthropic — Productivity</p>
        </div>
      ),
    },
    {
      kind: 'line',
      delay: 12400,
      render: (
        <p className="mt-3 text-white/85">
          <span className="text-emerald-400">{'›'}</span>{' '}
          <span className="italic">Compare keyword density between Threads and Instagram.</span>
        </p>
      ),
    },
    {
      kind: 'line',
      delay: 13200,
      render: (
        <div className="pl-3 border-l-2 border-emerald-500/40 text-white/70 space-y-0.5">
          <p>Top 3 shared keywords: <span className="text-white">photos · friends · share</span></p>
          <p>Threads-only top 3: <span className="text-white">text · post · feed</span></p>
          <p>Instagram-only top 3: <span className="text-white">reels · stories · explore</span></p>
        </div>
      ),
    },
  ]

  const [visibleCount, setVisibleCount] = useState(0)
  const scrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const timers = stages.map((s, i) =>
      setTimeout(() => setVisibleCount((c) => Math.max(c, i + 1)), s.delay),
    )
    return () => timers.forEach((t) => clearTimeout(t))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight
    }
  }, [visibleCount])

  return (
    <section className="py-16 md:py-24 border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-5xl">
        <div className="text-center mb-10 md:mb-12">
          <div className="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-full">
            <Terminal className="h-3 w-3" />
            Live demo
          </div>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight">
            From <span className="text-white/40">curl</span> to <span className="text-emerald-400">answer</span> in 60 seconds.
          </h2>
        </div>
        <div className="rounded-xl border border-white/10 bg-[#0a0a0a] overflow-hidden shadow-2xl shadow-emerald-500/5">
          <div className="flex items-center gap-2 px-4 py-3 bg-white/[0.02] border-b border-white/10">
            <div className="flex gap-1.5">
              <div className="w-3 h-3 rounded-full bg-red-500/70" />
              <div className="w-3 h-3 rounded-full bg-yellow-500/70" />
              <div className="w-3 h-3 rounded-full bg-emerald-500/70" />
            </div>
            <span className="ml-3 text-xs text-white/40 font-mono">terminal — appstorecat</span>
          </div>
          <div
            ref={scrollRef}
            className="p-5 md:p-6 font-mono text-[13px] md:text-sm h-[440px] md:h-[520px] overflow-y-auto space-y-1.5 [&_p]:break-words [&_p]:[overflow-wrap:anywhere]"
          >
            {stages.slice(0, visibleCount).map((s, i) => (
              <div key={i} className="animate-terminal-line min-w-0">
                {s.kind === 'line' ? s.render : <InlinePreview src={s.src} alt={s.alt} url={s.url} />}
              </div>
            ))}
            <span className="inline-block h-4 w-1.5 bg-emerald-400 align-middle animate-pulse" />
          </div>
        </div>
      </div>
    </section>
  )
}

function InlinePreview({ src, alt, url }: { src: string; alt: string; url: string }) {
  return (
    <div className="my-4 rounded-md border border-white/10 bg-white/[0.02] overflow-hidden">
      <div className="flex items-center gap-2 px-3 py-2 bg-white/[0.03] border-b border-white/10">
        <div className="flex gap-1">
          <div className="w-2 h-2 rounded-full bg-red-500/60" />
          <div className="w-2 h-2 rounded-full bg-yellow-500/60" />
          <div className="w-2 h-2 rounded-full bg-emerald-500/60" />
        </div>
        <div className="ml-2 flex-1 rounded-sm bg-black/40 px-2 py-0.5 text-[10px] text-white/40 truncate">
          {url}
        </div>
      </div>
      <img src={src} alt={alt} loading="lazy" className="block w-full h-auto" />
    </div>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// WHY — 3 USE-CASE PILLARS (Dub model)
// ────────────────────────────────────────────────────────────────────────────

function Why() {
  const pillars = [
    {
      icon: LineChart,
      label: 'For ASO teams',
      title: 'Stop paying $499/mo for keyword density.',
      copy:
        'N-gram analysis (1/2/3-word), 50-language stop-word filter, side-by-side comparison across up to 5 apps. Run it locally, never share your keyword research.',
      cta: { label: 'Keyword density', href: `${DOCS_URL}features/keyword-density/` },
    },
    {
      icon: Eye,
      label: 'For product teams',
      title: 'Watch competitors without alt-tabbing to the App Store.',
      copy:
        'Track every store listing for title, subtitle, description, screenshots, version, locale and price changes. Get the diff, the day, the country — automatically.',
      cta: { label: 'Change detection', href: `${DOCS_URL}features/change-detection/` },
    },
    {
      icon: Boxes,
      label: 'For indie hackers',
      title: 'A self-hosted Sensor Tower in 60 seconds.',
      copy:
        'One curl command, four Docker services, your own database. MIT-licensed forever, no SaaS lock-in, no per-app pricing. Your competitor list stays on your server.',
      cta: { label: 'Install guide', href: INSTALL_DOCS_URL },
    },
  ]

  return (
    <section id="why" className="py-20 md:py-28 border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-6xl">
        <div className="text-center mb-14 md:mb-20">
          <p className="text-sm font-medium text-emerald-400 mb-3">Built for three audiences</p>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight max-w-3xl mx-auto text-balance">
            One toolkit. Three jobs commercial tools overcharge for.
          </h2>
        </div>
        <div className="grid gap-6 md:gap-7 md:grid-cols-3">
          {pillars.map((p) => {
            const Icon = p.icon
            return (
              <div
                key={p.label}
                className="group relative rounded-xl border border-white/10 bg-gradient-to-b from-white/[0.03] to-transparent p-6 md:p-8 hover:border-emerald-500/30 transition-colors"
              >
                <div className="flex items-center gap-2 mb-5">
                  <div className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-emerald-500/10 border border-emerald-500/20">
                    <Icon className="h-4 w-4 text-emerald-400" />
                  </div>
                  <span className="text-xs uppercase tracking-wider text-white/50 font-medium">
                    {p.label}
                  </span>
                </div>
                <h3 className="text-xl md:text-2xl font-semibold text-white tracking-tight mb-3 leading-snug">
                  {p.title}
                </h3>
                <p className="text-sm md:text-[15px] text-white/60 leading-relaxed mb-5">
                  {p.copy}
                </p>
                <a
                  href={p.cta.href}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 text-sm text-emerald-400 hover:text-emerald-300 transition-colors"
                >
                  {p.cta.label}
                  <ArrowUpRight className="h-3.5 w-3.5" />
                </a>
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FEATURES (compact 6-card grid)
// ────────────────────────────────────────────────────────────────────────────

function Features() {
  const features = [
    {
      icon: Search,
      title: 'Discovery',
      copy: 'Search the App Store and Google Play, browse trending charts by country and category, import entire publisher catalogs.',
    },
    {
      icon: Eye,
      title: 'Competitor tracking',
      copy: 'Define competitor relationships, monitor their store presence side by side, track their daily moves.',
    },
    {
      icon: Bell,
      title: 'Change detection',
      copy: 'Title, subtitle, description, screenshots, version, locale, price — every change becomes a clean event with the old value.',
    },
    {
      icon: LineChart,
      title: 'Trending charts',
      copy: 'Daily snapshots of top free, top paid, and top grossing across both stores, with historical rank data.',
    },
    {
      icon: Globe,
      title: 'Multi-locale & multi-country',
      copy: 'Per-locale store listings, per-country availability tracking, ratings broken down by country.',
    },
    {
      icon: Zap,
      title: 'Keyword density',
      copy: '1/2/3-gram extraction, 50-language stop-word filtering, cross-app comparison up to 5 apps.',
    },
  ]
  return (
    <section id="features" className="py-20 md:py-28 border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-6xl">
        <div className="text-center mb-14">
          <p className="text-sm font-medium text-emerald-400 mb-3">Features</p>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight">
            Everything you need. Nothing you don't.
          </h2>
          <p className="mt-4 text-base md:text-lg text-white/60 max-w-xl mx-auto">
            What commercial tools charge $499/month for, packaged as MIT-licensed open source.
          </p>
        </div>
        <div className="grid gap-px bg-white/5 rounded-xl overflow-hidden border border-white/10 sm:grid-cols-2 lg:grid-cols-3">
          {features.map((f) => {
            const Icon = f.icon
            return (
              <div
                key={f.title}
                className="bg-[#0a0a0a] p-6 md:p-7 hover:bg-white/[0.02] transition-colors"
              >
                <div className="inline-flex items-center justify-center w-9 h-9 rounded-md bg-emerald-500/10 border border-emerald-500/20 mb-4">
                  <Icon className="h-4 w-4 text-emerald-400" />
                </div>
                <h3 className="text-base md:text-lg font-semibold text-white mb-2">{f.title}</h3>
                <p className="text-sm text-white/55 leading-relaxed">{f.copy}</p>
              </div>
            )
          })}
        </div>
        <div className="mt-10 text-center text-sm text-white/40">
          More:{' '}
          <a href={`${DOCS_URL}features/ratings/`} target="_blank" rel="noopener noreferrer" className="text-white/70 hover:text-white underline-offset-4 hover:underline">
            ratings history
          </a>
          ,{' '}
          <a href={`${DOCS_URL}features/app-rankings/`} target="_blank" rel="noopener noreferrer" className="text-white/70 hover:text-white underline-offset-4 hover:underline">
            rank pivots
          </a>
          ,{' '}
          <a href={`${DOCS_URL}features/publisher-discovery/`} target="_blank" rel="noopener noreferrer" className="text-white/70 hover:text-white underline-offset-4 hover:underline">
            publisher discovery
          </a>
          ,{' '}
          <a href={`${DOCS_URL}features/media-proxy/`} target="_blank" rel="noopener noreferrer" className="text-white/70 hover:text-white underline-offset-4 hover:underline">
            screenshot explorer
          </a>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// MCP — DEDICATED SECTION (the AI-era moat)
// ────────────────────────────────────────────────────────────────────────────

function McpSection() {
  return (
    <section id="mcp" className="relative py-20 md:py-28 border-b border-white/5 overflow-hidden">
      <div
        aria-hidden
        className="absolute inset-0 pointer-events-none opacity-50"
        style={{
          background:
            'radial-gradient(50% 50% at 50% 50%, rgba(16,185,129,0.12) 0%, transparent 70%)',
        }}
      />
      <div className="container mx-auto px-4 sm:px-6 max-w-6xl relative">
        <div className="grid lg:grid-cols-5 gap-10 lg:gap-16 items-center">
          <div className="lg:col-span-2">
            <div className="inline-flex items-center gap-2 px-3 py-1 mb-5 text-xs font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-full">
              <Sparkles className="h-3 w-3" />
              MCP-native — built for the AI era
            </div>
            <h2 className="text-3xl md:text-4xl lg:text-5xl font-semibold text-white tracking-tight leading-tight mb-5">
              Your store data,
              <br />
              <span className="bg-gradient-to-r from-emerald-300 to-emerald-400 bg-clip-text text-transparent">
                inside Claude Code.
              </span>
            </h2>
            <p className="text-base md:text-lg text-white/60 leading-relaxed mb-7">
              32 tools (28 read-only + 4 write) for apps, competitors, charts, ratings, keywords, changes,
              publishers, and the dashboard. Swagger-strict, chain-first.
              <br />
              <span className="text-white/80">Sensor Tower has none of this.</span>
            </p>
            <div className="flex flex-wrap items-center gap-3">
              <PrimaryButton href={MCP_DOCS_URL}>
                Read MCP docs
                <ArrowUpRight className="h-3.5 w-3.5" />
              </PrimaryButton>
              <GhostButton href={NPM_MCP_URL}>
                <NpmIcon className="h-4 w-4 text-red-400" />
                @appstorecat/mcp
              </GhostButton>
            </div>
          </div>
          <div className="lg:col-span-3">
            <div className="rounded-xl border border-white/10 bg-[#0a0a0a] overflow-hidden">
              <div className="px-5 py-3 border-b border-white/10 bg-white/[0.02] flex items-center gap-2">
                <Terminal className="h-3.5 w-3.5 text-emerald-400" />
                <span className="text-xs font-mono text-white/50">claude code · MCP config</span>
              </div>
              <pre className="p-5 md:p-6 text-[13px] md:text-sm font-mono text-white/85 overflow-x-auto leading-relaxed">
{`claude mcp add appstorecat \\
  -e APPSTORECAT_API_URL=http://localhost:7460/api/v1 \\
  -e APPSTORECAT_API_TOKEN=<your-token> \\
  -- npx -y `}<span className="text-emerald-400">@appstorecat/mcp</span>
              </pre>
              <div className="border-t border-white/10 p-5 md:p-6 space-y-3 text-sm">
                <div className="flex items-start gap-3">
                  <span className="text-emerald-400 font-mono text-xs mt-0.5">›</span>
                  <span className="italic text-white/85">
                    Which competitor changed their App Store screenshots this week?
                  </span>
                </div>
                <div className="pl-6 text-white/55 text-xs font-mono">
                  → calls <span className="text-emerald-400">list_competitor_changes</span> with{' '}
                  <span className="text-emerald-400">field=screenshots</span>
                </div>
                <div className="border-t border-white/5 pt-3 flex items-start gap-3">
                  <span className="text-emerald-400 font-mono text-xs mt-0.5">›</span>
                  <span className="italic text-white/85">
                    Compare top 5 weather apps' keyword density.
                  </span>
                </div>
                <div className="pl-6 text-white/55 text-xs font-mono">
                  → chains <span className="text-emerald-400">search_store_apps</span> →{' '}
                  <span className="text-emerald-400">compare_app_keywords</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// SELF-HOST / OPEN SOURCE
// ────────────────────────────────────────────────────────────────────────────

function SelfHost() {
  const cmd = useInstallCommand()
  const perks = [
    { icon: Lock, title: 'Your data stays on your server', copy: 'Competitor lists, keyword research, tracking history — never leaves your infrastructure.' },
    { icon: Boxes, title: 'Four Docker services + MySQL', copy: 'Laravel API gateway · React SPA · App Store scraper · Google Play scraper.' },
    { icon: Eye, title: 'Audit every line of source', copy: 'MIT licensed on GitHub. No closed binaries, no telemetry, no analytics ping.' },
    { icon: Zap, title: 'No per-app pricing', copy: 'Track 10 apps or 10,000 — same install, same toolkit, same $0 forever.' },
  ]
  return (
    <section id="install" className="py-20 md:py-28 border-b border-white/5 bg-white/[0.015]">
      <div className="container mx-auto px-4 sm:px-6 max-w-5xl">
        <div className="text-center mb-12 md:mb-16">
          <div className="inline-flex items-center gap-2 px-3 py-1 mb-4 text-xs font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 rounded-full">
            <Lock className="h-3 w-3" />
            100% open source · MIT
          </div>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight mb-4">
            Self-hosted. Or it's not yours.
          </h2>
          <p className="text-base md:text-lg text-white/60 max-w-2xl mx-auto">
            One command, four containers, your own server. No SaaS account, no API key from us, no
            telemetry.
          </p>
        </div>

        <div className="grid md:grid-cols-2 gap-5 mb-10">
          {perks.map((p) => {
            const Icon = p.icon
            return (
              <div
                key={p.title}
                className="rounded-xl border border-white/10 bg-[#0a0a0a] p-6 flex items-start gap-4"
              >
                <div className="inline-flex items-center justify-center w-10 h-10 rounded-md bg-emerald-500/10 border border-emerald-500/20 shrink-0">
                  <Icon className="h-4 w-4 text-emerald-400" />
                </div>
                <div>
                  <h3 className="text-base font-semibold text-white mb-1">{p.title}</h3>
                  <p className="text-sm text-white/55 leading-relaxed">{p.copy}</p>
                </div>
              </div>
            )
          })}
        </div>

        <div className="rounded-xl border border-emerald-500/20 bg-gradient-to-b from-emerald-500/[0.04] to-transparent p-6 md:p-8">
          <div className="text-center mb-5">
            <p className="text-xs uppercase tracking-wider text-emerald-400 mb-2">Install</p>
            <p className="text-white/70 text-sm">Requires Docker, git, make, curl. Tested on macOS &amp; Linux.</p>
          </div>
          <CopyableCommand cmd={cmd} className="max-w-2xl mx-auto" />
          <div className="mt-5 flex flex-wrap items-center justify-center gap-3">
            <GhostButton href={INSTALL_DOCS_URL}>
              What does this script do?
            </GhostButton>
            <GhostButton href={`${DOCS_URL}deployment/production/`}>
              Production deployment
            </GhostButton>
          </div>
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// COMPARISON STRIP (lightweight)
// ────────────────────────────────────────────────────────────────────────────

function Comparison() {
  const rows = [
    { feature: 'Open source', as: '✓ MIT', them: '✗ Closed' },
    { feature: 'Self-hostable', as: '✓ Docker, 60s', them: '✗ SaaS only' },
    { feature: 'Starting price', as: '$0', them: '$499–$1500/mo' },
    { feature: 'Per-app pricing', as: '✗ none', them: '✓ usually' },
    { feature: 'MCP server (Claude / Cursor)', as: '✓ 32 tools', them: '✗' },
    { feature: 'Data on your servers', as: '✓ always', them: '✗' },
    { feature: 'iOS + Android', as: '✓ both', them: '○ varies' },
    { feature: 'Audit the code', as: '✓ GitHub', them: '✗' },
  ]
  return (
    <section className="py-20 md:py-28 border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-4xl">
        <div className="text-center mb-12">
          <p className="text-sm font-medium text-emerald-400 mb-3">vs commercial alternatives</p>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight">
            Same data. Different deal.
          </h2>
        </div>
        <div className="rounded-xl border border-white/10 overflow-hidden">
          <div className="grid grid-cols-3 bg-white/[0.03] border-b border-white/10">
            <div className="px-5 py-4 text-xs uppercase tracking-wider text-white/40 font-medium">
              Capability
            </div>
            <div className="px-5 py-4 text-xs uppercase tracking-wider text-emerald-400 font-medium border-l border-white/10">
              AppStoreCat
            </div>
            <div className="px-5 py-4 text-xs uppercase tracking-wider text-white/40 font-medium border-l border-white/10">
              Sensor Tower / data.ai / AppFigures
            </div>
          </div>
          {rows.map((r, i) => (
            <div
              key={r.feature}
              className={`grid grid-cols-3 ${i < rows.length - 1 ? 'border-b border-white/5' : ''}`}
            >
              <div className="px-5 py-3.5 text-sm text-white/80">{r.feature}</div>
              <div className="px-5 py-3.5 text-sm text-emerald-400 font-medium border-l border-white/10">
                {r.as}
              </div>
              <div className="px-5 py-3.5 text-sm text-white/50 border-l border-white/10">
                {r.them}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

// ────────────────────────────────────────────────────────────────────────────
// FAQ
// ────────────────────────────────────────────────────────────────────────────

type QA = { q: string; a: ReactNode }

function FaqItem({ item, open, onToggle }: { item: QA; open: boolean; onToggle: () => void }) {
  return (
    <div className="border-b border-white/10">
      <button
        onClick={onToggle}
        className="w-full flex items-center justify-between gap-6 py-5 md:py-6 text-left"
      >
        <span className="text-base md:text-lg font-medium text-white">{item.q}</span>
        <ChevronDown
          className={`h-4 w-4 flex-shrink-0 text-white/50 transition-transform ${open ? 'rotate-180' : ''}`}
        />
      </button>
      {open && <div className="pb-6 pr-10 text-sm md:text-base text-white/60 leading-relaxed">{item.a}</div>}
    </div>
  )
}

function Faq() {
  const items: QA[] = [
    {
      q: 'How is this different from Sensor Tower or data.ai?',
      a: (
        <>
          They're closed-source SaaS at $499–$1500/month, with your data on their servers.
          AppStoreCat is MIT-licensed open source, runs on your infrastructure, has no per-app
          pricing, and ships with a 32-tool MCP server so Claude Code / Cursor / Continue can query
          your data directly. What it doesn't do: paid-tier estimates like revenue and downloads —
          those require commercial data partnerships.
        </>
      ),
    },
    {
      q: 'What does the install script actually do?',
      a: (
        <>
          Verifies dependencies, clones the repo, copies <code className="px-1 bg-white/5 rounded text-[13px]">.env.development.example</code> →{' '}
          <code className="px-1 bg-white/5 rounded text-[13px]">.env</code>, runs{' '}
          <code className="px-1 bg-white/5 rounded text-[13px]">make setup &amp;&amp; make dev</code>, then
          waits for the frontend to respond. Doesn't require root, doesn't write outside the install
          directory, doesn't phone home.{' '}
          <a
            href={INSTALL_DOCS_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="text-emerald-400 hover:underline"
          >
            Full breakdown
          </a>
          .
        </>
      ),
    },
    {
      q: 'How does the MCP server work?',
      a: (
        <>
          The MCP server is a Node.js process that Claude Code spawns over stdio. It translates MCP
          tool calls into authenticated HTTP requests to your AppStoreCat instance. 32
          tools cover apps, competitors, changes, charts, ratings, keywords, publishers, and the
          dashboard. Every tool's input mirrors the OpenAPI schema exactly (Swagger-strict);
          response IDs are preserved so the LLM can plan multi-step lookups (chain-first).{' '}
          <a
            href={MCP_DOCS_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="text-emerald-400 hover:underline"
          >
            MCP docs
          </a>
          .
        </>
      ),
    },
    {
      q: 'Which stores does it support?',
      a: 'Apple App Store (iOS) and Google Play Store (Android). Both at full feature parity — search, track, compare, listings, reviews, ratings, keyword density, charts, and change detection across hundreds of countries.',
    },
    {
      q: 'What runtime do I need?',
      a: 'Docker Engine 24+, Compose v2, git, make, curl. 2 vCPU and 4 GB RAM is enough for a comfortable single-host install. macOS, Linux, and WSL2 on Windows all work.',
    },
    {
      q: 'How does change detection work?',
      a: 'Tracked apps sync on a 20-minute scheduler in small batches. When the store listing changes — title, subtitle, description, screenshots, version, locale, price — the diff is captured per locale, per country, per field, and recorded with a timestamp. You get a clean event timeline, not a noisy feed.',
    },
    {
      q: 'Is there a hosted version?',
      a: (
        <>
          Yes — <a href="https://appstore.cat" className="text-emerald-400 hover:underline">appstore.cat</a> runs the same code, free for the demo. The recommended path is still self-host. The hosted instance exists so you can click around before installing.
        </>
      ),
    },
    {
      q: "What's not in the box?",
      a: 'Revenue and download estimates (those need paid data partnerships), webhook notifications, Slack/email alerts, multi-user workspaces, advanced ASO scoring. Several of these are on the roadmap; the rest is what commercial tools earn their price tag for.',
    },
  ]
  const [openIdx, setOpenIdx] = useState<number | null>(0)
  return (
    <section id="faq" className="py-20 md:py-28 border-b border-white/5">
      <div className="container mx-auto px-4 sm:px-6 max-w-3xl">
        <div className="text-center mb-12 md:mb-14">
          <p className="text-sm font-medium text-emerald-400 mb-3">FAQ</p>
          <h2 className="text-3xl sm:text-4xl md:text-5xl font-semibold text-white tracking-tight">
            Common questions.
          </h2>
        </div>
        <div className="border-t border-white/10">
          {items.map((it, i) => (
            <FaqItem
              key={i}
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
  const cmd = useInstallCommand()
  return (
    <section className="relative py-24 md:py-32 overflow-hidden">
      <div
        aria-hidden
        className="absolute inset-0 pointer-events-none opacity-50"
        style={{
          background:
            'radial-gradient(60% 50% at 50% 50%, rgba(16,185,129,0.18) 0%, transparent 70%)',
        }}
      />
      <div className="container mx-auto px-4 sm:px-6 max-w-3xl relative">
        <div className="text-center">
          <h2 className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-semibold text-white tracking-tight leading-tight mb-5">
            Run your own app intelligence stack.
            <br />
            <span className="text-white/40">In one command.</span>
          </h2>
          <p className="text-base md:text-lg text-white/60 mb-9 max-w-xl mx-auto">
            Free, MIT-licensed, MCP-native. Five minutes to your first scrape.
          </p>
          <div className="max-w-xl mx-auto">
            <CopyableCommand cmd={cmd} />
          </div>
          <div className="mt-5 flex flex-wrap justify-center items-center gap-3">
            <PrimaryCta />
            <GhostButton href={GITHUB_URL}>
              <GithubIcon className="h-4 w-4" />
              Star on GitHub
            </GhostButton>
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
        { label: 'MCP Server', href: '#mcp' },
        { label: 'Install', href: '#install' },
        { label: 'Live Demo', href: '/discovery/trending' },
      ],
    },
    {
      title: 'Tools',
      links: [
        { label: 'App Search', href: '/apps/search' },
        { label: 'Trending', href: '/discovery/trending' },
        { label: 'Publishers', href: '/publishers' },
        { label: 'Changes', href: '/changes' },
      ],
    },
    {
      title: 'Resources',
      links: [
        { label: 'Documentation', href: DOCS_URL, external: true },
        { label: 'Install Script', href: INSTALL_DOCS_URL, external: true },
        { label: 'MCP Docs', href: MCP_DOCS_URL, external: true },
        { label: 'Changelog', href: `${GITHUB_URL}/releases`, external: true },
      ],
    },
    {
      title: 'Open Source',
      links: [
        { label: 'GitHub', href: GITHUB_URL, external: true },
        { label: 'Issues', href: `${GITHUB_URL}/issues`, external: true },
        { label: 'Contributing', href: `${GITHUB_URL}/blob/master/CONTRIBUTING.md`, external: true },
        { label: 'License (MIT)', href: `${GITHUB_URL}/blob/master/LICENSE`, external: true },
      ],
    },
  ]
  return (
    <footer className="border-t border-white/5 bg-white/[0.01]">
      <div className="container mx-auto px-4 sm:px-6 py-14 md:py-16 max-w-6xl">
        <div className="grid grid-cols-2 gap-8 sm:grid-cols-3 md:grid-cols-5 md:gap-10">
          <div className="col-span-2 sm:col-span-3 md:col-span-1">
            <Link to="/" className="flex items-center gap-2.5">
              <Logo className="h-7 w-7" />
              <span className="text-base font-semibold text-white">appstorecat</span>
            </Link>
            <p className="mt-4 text-sm text-white/50 leading-relaxed">
              Open-source App Store &amp; Google Play intelligence. MIT licensed. MCP-native.
            </p>
          </div>
          {columns.map((col) => (
            <div key={col.title}>
              <h4 className="text-xs font-semibold uppercase tracking-widest text-white/40 mb-4">
                {col.title}
              </h4>
              <ul className="space-y-2.5">
                {col.links.map((l) => (
                  <li key={l.label}>
                    {l.external ? (
                      <a
                        href={l.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-white/65 hover:text-white transition-colors"
                      >
                        {l.label}
                      </a>
                    ) : (
                      <Link
                        to={l.href}
                        className="text-sm text-white/65 hover:text-white transition-colors"
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
        <div className="mt-12 pt-8 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4">
          <p className="text-xs text-white/40">
            © 2026 AppStoreCat · MIT License · No telemetry, no tracking.
          </p>
          <div className="flex items-center gap-4">
            <a
              href={NPM_MCP_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white transition-colors"
            >
              <NpmIcon className="h-3.5 w-3.5" />
              @appstorecat/mcp
            </a>
            <a
              href={GITHUB_URL}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-xs text-white/40 hover:text-white transition-colors"
            >
              <GithubIcon className="h-3.5 w-3.5" />
              github.com/appstorecat
            </a>
          </div>
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
    <div className="min-h-screen bg-black text-white antialiased selection:bg-emerald-500/30 selection:text-white">
      <Header />
      <main className="pt-16">
        <Hero />
        <MetricStrip />
        <LiveDemo />
        <Why />
        <Features />
        <McpSection />
        <SelfHost />
        <Comparison />
        <Faq />
        <FinalCta />
      </main>
      <Footer />
    </div>
  )
}
