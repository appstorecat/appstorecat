import { Link } from 'react-router-dom'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

const GITHUB_URL = 'https://github.com/appstorecat/appstorecat'
const INSTALL_CMD = `curl -sSL ${window.location.origin}/install.sh | sh`

function Nav() {
  return (
    <nav className="sticky top-0 z-50 border-b border-white/10 bg-[#0a0a0f]/80 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
        <div className="flex items-center gap-2">
          <img src="/appstorecat-icon.svg" alt="AppStoreCat" className="h-8 w-8 rounded-md" />
          <span className="text-lg font-bold tracking-wide text-white uppercase">AppStoreCat</span>
        </div>
        <div className="hidden items-center gap-8 md:flex">
          <a href="#features" className="text-sm text-white/60 hover:text-white transition-colors">Features</a>
          <a href="#comparison" className="text-sm text-white/60 hover:text-white transition-colors">Why Open Source</a>
          <a href="#quickstart" className="text-sm text-white/60 hover:text-white transition-colors">Quick Start</a>
          <a href={GITHUB_URL} target="_blank" rel="noopener noreferrer" className="text-sm text-white/60 hover:text-white transition-colors flex items-center gap-1.5">
            <GithubIcon className="h-4 w-4" />
            GitHub
          </a>
        </div>
        <div className="flex items-center gap-3">
          <Link to="/login" className="inline-flex items-center rounded-lg px-3 h-8 text-sm text-white/70 hover:text-white hover:bg-white/10 transition-colors">
            Sign In
          </Link>
          <Link to="/register" className="inline-flex items-center rounded-lg bg-emerald-500 px-4 h-8 text-sm font-medium text-black hover:bg-emerald-400 transition-colors">
            Get Started
          </Link>
        </div>
      </div>
    </nav>
  )
}

function StarField() {
  return (
    <div className="absolute inset-0 overflow-hidden">
      {/* Base dark */}
      <div className="absolute inset-0 bg-[#0a0a0f]" />
      {/* Nebula glow */}
      <div className="absolute top-[-20%] left-[-10%] w-[60%] h-[60%] rounded-full bg-emerald-500/5 blur-[120px]" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[50%] h-[50%] rounded-full bg-blue-500/5 blur-[120px]" />
      <div className="absolute top-[30%] right-[20%] w-[30%] h-[30%] rounded-full bg-emerald-500/5 blur-[100px]" />
      {/* Star dots */}
      <div className="absolute inset-0" style={{
        backgroundImage: `radial-gradient(1px 1px at 20px 30px, rgba(255,255,255,0.3), transparent),
                          radial-gradient(1px 1px at 40px 70px, rgba(255,255,255,0.2), transparent),
                          radial-gradient(1px 1px at 50px 160px, rgba(255,255,255,0.3), transparent),
                          radial-gradient(1px 1px at 90px 40px, rgba(255,255,255,0.15), transparent),
                          radial-gradient(1.5px 1.5px at 130px 80px, rgba(255,255,255,0.4), transparent),
                          radial-gradient(1px 1px at 160px 120px, rgba(255,255,255,0.2), transparent),
                          radial-gradient(1px 1px at 200px 20px, rgba(255,255,255,0.25), transparent),
                          radial-gradient(1.5px 1.5px at 220px 170px, rgba(255,255,255,0.35), transparent),
                          radial-gradient(1px 1px at 260px 50px, rgba(255,255,255,0.2), transparent),
                          radial-gradient(1px 1px at 300px 140px, rgba(255,255,255,0.3), transparent)`,
        backgroundSize: '320px 200px',
      }} />
      {/* Grid overlay */}
      <div className="absolute inset-0 opacity-[0.03]" style={{
        backgroundImage: `linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px)`,
        backgroundSize: '60px 60px',
      }} />
    </div>
  )
}

function Hero() {
  return (
    <section className="relative overflow-hidden py-24 md:py-32">
      <StarField />
      <div className="relative mx-auto max-w-6xl px-6 text-center">
        <div className="mb-6 flex justify-center">
          <Badge className="gap-1.5 border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-xs text-emerald-400 hover:bg-emerald-500/15">
            <span className="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" />
            100% Open Source &middot; Self-Hosted &middot; Free Forever
          </Badge>
        </div>
        <h1 className="mx-auto max-w-4xl text-4xl font-bold tracking-tight text-white md:text-6xl lg:text-7xl">
          App Intelligence
          <br />
          <span className="bg-gradient-to-r from-emerald-400 to-emerald-400 bg-clip-text text-transparent">You Actually Own</span>
        </h1>
        <p className="mx-auto mt-6 max-w-2xl text-lg text-white/60 md:text-xl">
          Track store listings, monitor changes, analyze keywords, and discover trending apps across iOS and Android. Deploy on your own infrastructure. No subscriptions. No data leaves your server.
        </p>

        {/* Install command */}
        <div className="mt-10 flex flex-col items-center gap-6">
          <div className="group relative w-full max-w-xl">
            <div className="absolute -inset-0.5 rounded-xl bg-gradient-to-r from-emerald-500/20 to-emerald-500/20 opacity-0 blur transition-opacity group-hover:opacity-100" />
            <div className="relative flex items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-5 py-3.5 font-mono text-sm backdrop-blur-sm">
              <span className="text-emerald-400 select-none">$</span>
              <code className="flex-1 text-white/80 text-left truncate">{INSTALL_CMD}</code>
              <button
                onClick={() => navigator.clipboard.writeText(INSTALL_CMD)}
                className="shrink-0 rounded p-1.5 hover:bg-white/10 transition-colors"
                title="Copy to clipboard"
              >
                <CopyIcon className="h-4 w-4 text-white/40" />
              </button>
            </div>
          </div>

          <div className="flex gap-3">
            <Link to="/register" className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-8 h-12 text-sm font-semibold text-black hover:bg-emerald-400 transition-colors">
              <RocketIcon className="h-4 w-4" />
              Get Started
            </Link>
            <a href={GITHUB_URL} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-8 h-12 text-sm font-semibold text-white hover:bg-white/10 transition-colors">
              <GithubIcon className="h-4 w-4" />
              Star on GitHub
            </a>
          </div>
        </div>

        {/* Dashboard screenshot */}
        <div className="relative mt-16">
          <div className="absolute -inset-4 rounded-2xl bg-gradient-to-b from-emerald-500/10 via-transparent to-transparent blur-xl" />
          <div className="relative overflow-hidden rounded-xl border border-white/10 shadow-2xl shadow-emerald-500/5">
            <img
              src="/screenshots/hero-dashboard.jpeg"
              alt="AppStoreCat Dashboard"
              className="w-full"
            />
          </div>
        </div>
      </div>
    </section>
  )
}

const features = [
  {
    icon: <TrendingIcon className="h-5 w-5" />,
    title: 'Trending Charts',
    description: 'Daily snapshots of top free, paid, and grossing apps across both stores with historical ranking data.',
  },
  {
    icon: <SearchIcon className="h-5 w-5" />,
    title: 'App Discovery',
    description: 'Search apps across App Store and Google Play, discover through charts, or import entire publisher catalogs.',
  },
  {
    icon: <ListIcon className="h-5 w-5" />,
    title: 'Store Listings',
    description: 'Multi-language listing tracking with title, description, screenshots, and metadata for each locale.',
  },
  {
    icon: <StarIcon className="h-5 w-5" />,
    title: 'Ratings & Reviews',
    description: 'Monitor rating trends and sync user reviews with filtering by country, rating, and date.',
  },
  {
    icon: <KeyIcon className="h-5 w-5" />,
    title: 'Keyword Density',
    description: 'ASO-focused keyword analysis with n-gram extraction, stop word filtering for 50 languages, and cross-app comparison.',
  },
  {
    icon: <DiffIcon className="h-5 w-5" />,
    title: 'Change Detection',
    description: 'Automatic detection of listing changes — title, description, screenshots, locales — with old/new value tracking.',
  },
  {
    icon: <TargetIcon className="h-5 w-5" />,
    title: 'Competitor Tracking',
    description: 'Define competitor relationships and monitor their store presence side by side with your apps.',
  },
  {
    icon: <BuildingIcon className="h-5 w-5" />,
    title: 'Publisher Discovery',
    description: 'Search publishers, view their full app catalogs, and bulk import all their apps in one click.',
  },
]

function Features() {
  return (
    <section id="features" className="relative py-24 bg-[#0a0a0f]">
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-500/[0.02] to-transparent" />
      <div className="relative mx-auto max-w-6xl px-6">
        <div className="text-center">
          <Badge className="mb-4 border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/15">Features</Badge>
          <h2 className="text-3xl font-bold tracking-tight text-white md:text-4xl">
            Everything you need for app intelligence
          </h2>
          <p className="mx-auto mt-4 max-w-2xl text-white/50">
            A complete toolkit for tracking, analyzing, and understanding app store performance — both platforms, one dashboard.
          </p>
        </div>
        <div className="mt-16 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
          {features.map((feature) => (
            <Card key={feature.title} className="group border-white/10 bg-white/[0.03] p-6 hover:bg-white/[0.06] hover:border-emerald-500/20 transition-all duration-300">
              <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-400 group-hover:bg-emerald-500/20 transition-colors">
                {feature.icon}
              </div>
              <h3 className="font-semibold text-white">{feature.title}</h3>
              <p className="mt-2 text-sm text-white/50 leading-relaxed">{feature.description}</p>
            </Card>
          ))}
        </div>
      </div>
    </section>
  )
}

function AIReady() {
  return (
    <section className="relative py-24 bg-[#0a0a0f]">
      <div className="mx-auto max-w-6xl px-6">
        <div className="text-center">
          <Badge className="mb-4 border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/15">AI-Powered</Badge>
          <h2 className="text-3xl font-bold tracking-tight text-white md:text-4xl">
            Connect your AI tools directly
          </h2>
          <p className="mx-auto mt-4 max-w-2xl text-white/50">
            Built-in MCP (Model Context Protocol) server lets Claude, Cursor, and other AI tools access your app intelligence data natively.
          </p>
        </div>
        <div className="mt-12 mx-auto max-w-2xl">
          <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] p-8">
            <p className="text-sm text-emerald-400 font-medium mb-4">Add to your MCP config:</p>
            <div className="rounded-lg bg-black/50 border border-white/10 p-4 font-mono text-sm">
              <pre className="text-white/70">{`{
  "mcpServers": {
    "appstorecat": {
      "url": "`}<span className="text-emerald-400">{window.location.origin}</span>{`/mcp",
      "headers": {
        "Authorization": "Bearer `}<span className="text-white/40">{'<your-api-token>'}</span>{`"
      }
    }
  }
}`}</pre>
            </div>
            <p className="mt-4 text-xs text-white/40">
              Generate your API token from Settings, add it to your MCP config, and your AI assistant can search apps, analyze keywords, and query your entire app intelligence database.
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

const comparisons = [
  { feature: 'Store listing tracking', us: true, them: true },
  { feature: 'Keyword analysis', us: true, them: true },
  { feature: 'Review monitoring', us: true, them: true },
  { feature: 'Competitor tracking', us: true, them: true },
  { feature: 'Trending charts', us: true, them: true },
  { feature: 'Change detection', us: true, them: 'Partial' },
  { feature: 'Self-hosted', us: true, them: false },
  { feature: 'Your data, your server', us: true, them: false },
  { feature: 'No vendor lock-in', us: true, them: false },
  { feature: 'API access', us: true, them: 'Paid' },
  { feature: 'MCP Server (AI-ready)', us: true, them: false },
  { feature: 'Source code access', us: true, them: false },
  { feature: 'Price', us: 'Free', them: '$99-499/mo' },
]

function Comparison() {
  return (
    <section id="comparison" className="relative py-24 bg-[#0a0a0f]">
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-500/[0.02] to-transparent" />
      <div className="relative mx-auto max-w-3xl px-6">
        <div className="text-center">
          <Badge className="mb-4 border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/15">Why Open Source</Badge>
          <h2 className="text-3xl font-bold tracking-tight text-white md:text-4xl">
            Stop renting your app data
          </h2>
          <p className="mx-auto mt-4 max-w-2xl text-white/50">
            Paid tools charge $99-499/month for data that should be yours. AppStoreCat gives you the same capabilities on your own infrastructure, forever free.
          </p>
        </div>
        <div className="mt-12 overflow-hidden rounded-xl border border-white/10">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-white/10 bg-white/[0.03]">
                <th className="px-6 py-4 text-left font-medium text-white/40"></th>
                <th className="px-6 py-4 text-center font-semibold">
                  <div className="flex items-center justify-center gap-2 text-emerald-400">
                    <img src="/appstorecat-icon.svg" alt="" className="h-5 w-5 rounded" />
                    AppStoreCat
                  </div>
                </th>
                <th className="px-6 py-4 text-center font-medium text-white/40">Paid Tools</th>
              </tr>
            </thead>
            <tbody>
              {comparisons.map((row, i) => (
                <tr key={row.feature} className={`border-b border-white/5 ${i % 2 === 0 ? '' : 'bg-white/[0.02]'}`}>
                  <td className="px-6 py-3 text-white/70">{row.feature}</td>
                  <td className="px-6 py-3 text-center">
                    {row.us === true ? <CheckIcon className="mx-auto h-4 w-4 text-emerald-400" /> :
                     <span className="font-bold text-emerald-400">{row.us}</span>}
                  </td>
                  <td className="px-6 py-3 text-center">
                    {row.them === true ? <CheckIcon className="mx-auto h-4 w-4 text-white/30" /> :
                     row.them === false ? <XIcon className="mx-auto h-4 w-4 text-red-400/50" /> :
                     <span className="text-xs text-white/40">{row.them}</span>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  )
}

function SelfHosted() {
  return (
    <section className="relative py-24 bg-[#0a0a0f]">
      <div className="mx-auto max-w-6xl px-6">
        <div className="grid gap-12 md:grid-cols-3">
          <div className="text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/20 to-emerald-500/20 ring-1 ring-emerald-500/20">
              <ShieldIcon className="h-6 w-6 text-emerald-400" />
            </div>
            <h3 className="text-lg font-semibold text-white">Your Data, Your Server</h3>
            <p className="mt-2 text-sm text-white/50">
              All data stays on your infrastructure. No third-party analytics, no tracking, no data sharing. Full privacy by default.
            </p>
          </div>
          <div className="text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500/20 to-cyan-500/20 ring-1 ring-blue-500/20">
              <CodeIcon className="h-6 w-6 text-blue-400" />
            </div>
            <h3 className="text-lg font-semibold text-white">Fully Open Source</h3>
            <p className="mt-2 text-sm text-white/50">
              MIT licensed. Read the code, modify it, contribute back. No hidden features, no premium tiers, no artificial limits.
            </p>
          </div>
          <div className="text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500/20 to-emerald-500/20 ring-1 ring-emerald-500/20">
              <BrainIcon className="h-6 w-6 text-emerald-400" />
            </div>
            <h3 className="text-lg font-semibold text-white">MCP Server Ready</h3>
            <p className="mt-2 text-sm text-white/50">
              Built-in Model Context Protocol server. Connect your AI tools directly to your app intelligence data for automated analysis.
            </p>
          </div>
        </div>
      </div>
    </section>
  )
}

function QuickStart() {
  return (
    <section id="quickstart" className="relative py-24 bg-[#0a0a0f]">
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-emerald-500/[0.02] to-transparent" />
      <div className="relative mx-auto max-w-3xl px-6 text-center">
        <Badge className="mb-4 border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/15">Quick Start</Badge>
        <h2 className="text-3xl font-bold tracking-tight text-white md:text-4xl">
          Deploy on your own server
        </h2>
        <p className="mx-auto mt-4 max-w-xl text-white/50">
          One command sets up everything on your infrastructure. Your data never leaves your server.
        </p>

        {/* Primary install */}
        <div className="mt-10 group relative w-full max-w-xl mx-auto">
          <div className="absolute -inset-0.5 rounded-xl bg-gradient-to-r from-emerald-500/30 to-emerald-500/30 blur opacity-50" />
          <div className="relative flex items-center gap-3 rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-5 py-4 font-mono text-sm">
            <span className="text-emerald-400 select-none">$</span>
            <code className="flex-1 text-emerald-300/90 text-left">{INSTALL_CMD}</code>
            <button
              onClick={() => navigator.clipboard.writeText(INSTALL_CMD)}
              className="shrink-0 rounded p-1.5 hover:bg-white/10 transition-colors"
              title="Copy to clipboard"
            >
              <CopyIcon className="h-4 w-4 text-emerald-400/50" />
            </button>
          </div>
        </div>

        <p className="mt-6 text-sm text-white/40">Or manually:</p>

        <div className="mt-4 space-y-3 text-left max-w-xl mx-auto">
          {[
            { step: '1', label: 'Clone the repository', cmd: 'git clone https://github.com/appstorecat/appstorecat.git' },
            { step: '2', label: 'Build and setup', cmd: 'cd appstorecat && make setup' },
            { step: '3', label: 'Start all services', cmd: 'make dev' },
          ].map(({ step, label, cmd }) => (
            <div key={step} className="flex items-center gap-4 rounded-lg border border-white/10 bg-white/[0.03] p-4">
              <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400 text-sm font-bold">
                {step}
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-white">{label}</p>
                <code className="text-xs text-white/40 truncate block">{cmd}</code>
              </div>
            </div>
          ))}
        </div>
        <p className="mt-8 text-sm text-white/40">
          Open <code className="rounded bg-white/10 px-1.5 py-0.5 text-emerald-400/70">https://{'{your-domain}'}.com</code> and create your account. That's it.
        </p>
        <div className="mt-8 flex justify-center gap-3">
          <Link to="/register" className="inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-8 h-12 text-sm font-semibold text-black hover:bg-emerald-400 transition-colors">
            Get Started
          </Link>
          <a href={`${GITHUB_URL}/blob/master/docs/en/getting-started/installation.md`} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/5 px-8 h-12 text-sm font-semibold text-white hover:bg-white/10 transition-colors">
            Read the Docs
          </a>
        </div>
      </div>
    </section>
  )
}

function Footer() {
  return (
    <footer className="border-t border-white/10 bg-[#0a0a0f] py-12">
      <div className="mx-auto max-w-6xl px-6">
        <div className="grid gap-8 md:grid-cols-4">
          <div>
            <div className="flex items-center gap-2">
              <img src="/appstorecat-icon.svg" alt="AppStoreCat" className="h-6 w-6 rounded" />
              <span className="font-bold tracking-wide text-white uppercase">AppStoreCat</span>
            </div>
            <p className="mt-3 text-sm text-white/40">
              Open-source app intelligence toolkit for iOS and Android.
            </p>
          </div>
          <div>
            <h4 className="font-medium text-white mb-3">Product</h4>
            <ul className="space-y-2 text-sm text-white/40">
              <li><a href="#features" className="hover:text-white transition-colors">Features</a></li>
              <li><a href="#quickstart" className="hover:text-white transition-colors">Quick Start</a></li>
              <li><a href={`${GITHUB_URL}/blob/master/docs/en/getting-started/installation.md`} className="hover:text-white transition-colors">Documentation</a></li>
              <li><a href={`${GITHUB_URL}/blob/master/ROADMAP.md`} className="hover:text-white transition-colors">Roadmap</a></li>
            </ul>
          </div>
          <div>
            <h4 className="font-medium text-white mb-3">Community</h4>
            <ul className="space-y-2 text-sm text-white/40">
              <li><a href={GITHUB_URL} className="hover:text-white transition-colors">GitHub</a></li>
              <li><a href={`${GITHUB_URL}/issues`} className="hover:text-white transition-colors">Issues</a></li>
              <li><a href={`${GITHUB_URL}/discussions`} className="hover:text-white transition-colors">Discussions</a></li>
              <li><a href={`${GITHUB_URL}/blob/master/CONTRIBUTING.md`} className="hover:text-white transition-colors">Contributing</a></li>
            </ul>
          </div>
          <div>
            <h4 className="font-medium text-white mb-3">Legal</h4>
            <ul className="space-y-2 text-sm text-white/40">
              <li><a href={`${GITHUB_URL}/blob/master/LICENSE`} className="hover:text-white transition-colors">MIT License</a></li>
              <li><a href={`${GITHUB_URL}/blob/master/SECURITY.md`} className="hover:text-white transition-colors">Security</a></li>
              <li><a href={`${GITHUB_URL}/blob/master/CODE_OF_CONDUCT.md`} className="hover:text-white transition-colors">Code of Conduct</a></li>
            </ul>
          </div>
        </div>
        <Separator className="my-8 bg-white/10" />
        <div className="flex items-center justify-between text-sm text-white/30">
          <p>&copy; {new Date().getFullYear()} AppStoreCat. Released under the MIT License.</p>
          <a href={GITHUB_URL} target="_blank" rel="noopener noreferrer" className="hover:text-white transition-colors">
            <GithubIcon className="h-5 w-5" />
          </a>
        </div>
      </div>
    </footer>
  )
}

// ─── Icons ──────────────────────────────────────────────────

function GithubIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className}>
      <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
    </svg>
  )
}

function CopyIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
    </svg>
  )
}

function RocketIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>
    </svg>
  )
}

function TrendingIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>
    </svg>
  )
}

function SearchIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
    </svg>
  )
}

function ListIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/>
    </svg>
  )
}

function StarIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
    </svg>
  )
}

function KeyIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z"/><circle cx="16.5" cy="7.5" r=".5" fill="currentColor"/>
    </svg>
  )
}

function DiffIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M12 3v14"/><path d="M5 10h14"/><path d="M5 21h14"/>
    </svg>
  )
}

function TargetIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>
    </svg>
  )
}

function BuildingIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>
    </svg>
  )
}

function ShieldIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
    </svg>
  )
}

function CodeIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>
    </svg>
  )
}

function BrainIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M19.938 10.5a4 4 0 0 1 .585.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M19.967 17.484A4 4 0 0 1 18 18"/>
    </svg>
  )
}

function CheckIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <polyline points="20 6 9 17 4 12"/>
    </svg>
  )
}

function XIcon({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}>
      <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
    </svg>
  )
}

// ─── Page ───────────────────────────────────────────────────

export default function Landing() {
  return (
    <div className="min-h-screen bg-[#0a0a0f] text-white">
      <Nav />
      <Hero />
      <Features />
      <AIReady />
      <Comparison />
      <SelfHosted />
      <QuickStart />
      <Footer />
    </div>
  )
}
