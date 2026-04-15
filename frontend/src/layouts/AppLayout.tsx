import { Outlet, Link, useLocation, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Building2, TrendingUp, Compass, Key, Camera, Image, KeyRound, Webhook, BookOpen, Smartphone, Users, FileSearch, FileSearch2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarRail,
  SidebarTrigger,
} from '@/components/ui/sidebar'
import AppLogo from '@/components/AppLogo'
import NavUser from '@/components/NavUser'
import Breadcrumbs, { type BreadcrumbItemData } from '@/components/Breadcrumbs'
import axios from '@/lib/axios'
import { useEffect } from 'react'

function useAppData() {
  const params = useParams()
  const location = useLocation()
  const isAppPage = location.pathname.startsWith('/apps/') && !!params.platform && !!params.externalId

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const { data } = useQuery<any>({
    queryKey: ['apps', params.platform, params.externalId],
    queryFn: () => axios.get(`/apps/${params.platform}/${params.externalId}`).then((r) => r.data),
    enabled: isAppPage,
  })

  return isAppPage ? data : null
}

function usePageTitle(app: { name?: string } | null) {
  const location = useLocation()

  useEffect(() => {
    const path = location.pathname
    let title = 'AppStoreCat'

    if (path === '/discovery/apps') title = 'Discover Apps — AppStoreCat'
    else if (path === '/discovery/publishers') title = 'Discover Publishers — AppStoreCat'
    else if (path === '/discovery/trending') title = 'Trending — AppStoreCat'
    else if (path === '/apps') title = 'Apps — AppStoreCat'
    else if (path === '/changes/apps') title = 'App Changes — AppStoreCat'
    else if (path === '/changes/competitors') title = 'Competitor Changes — AppStoreCat'
    else if (path === '/publishers' || path.startsWith('/publishers/')) title = 'Publishers — AppStoreCat'
    else if (path === '/competitors') title = 'Competitors — AppStoreCat'
    else if (path === '/explorer/screenshots') title = 'Screenshots — AppStoreCat'
    else if (path === '/explorer/icons') title = 'App Icons — AppStoreCat'
    else if (path === '/settings') title = 'Settings — AppStoreCat'
    else if (app?.name) title = `${app.name} — AppStoreCat`

    document.title = title
  }, [location.pathname, app?.name])
}

function useBreadcrumbs(app: { name?: string; platform?: string; publisher?: { name?: string; external_id?: string; platform?: string } } | null): BreadcrumbItemData[] {
  const location = useLocation()
  const path = location.pathname

  if (path === '/discovery/apps') {
    return [{ title: 'Discovery', href: '/discovery/apps' }, { title: 'Apps' }]
  }

  if (path === '/discovery/publishers') {
    return [{ title: 'Discovery', href: '/discovery/apps' }, { title: 'Publishers' }]
  }

  if (path === '/discovery/trending') {
    return [{ title: 'Discovery', href: '/discovery/apps' }, { title: 'Trending' }]
  }

  if (path === '/apps') {
    return [{ title: 'Apps', href: '/apps' }]
  }

  if (path === '/publishers') {
    return [{ title: 'Publishers', href: '/publishers' }]
  }

  if (path.startsWith('/publishers/')) {
    return [
      { title: 'Publishers', href: '/publishers' },
      { title: 'Publisher' },
    ]
  }

  if (path === '/competitors') {
    return [{ title: 'Competitors', href: '/competitors' }]
  }

  if (path === '/changes/apps') {
    return [{ title: 'App Changes', href: '/changes/apps' }]
  }

  if (path === '/changes/competitors') {
    return [{ title: 'Competitor Changes', href: '/changes/competitors' }]
  }

  if (path === '/explorer/screenshots') {
    return [{ title: 'Explorer' }, { title: 'Screenshots', href: '/explorer/screenshots' }]
  }

  if (path === '/explorer/icons') {
    return [{ title: 'Explorer' }, { title: 'App Icons', href: '/explorer/icons' }]
  }

  if (path === '/settings') {
    return [{ title: 'Settings', href: '/settings' }]
  }

  if (path.startsWith('/apps/') && app) {
    const items: BreadcrumbItemData[] = [
      { title: 'Apps', href: '/apps' },
    ]
    if (app.publisher?.name && app.publisher.external_id) {
      const pubPlatform = app.publisher.platform ?? app.platform ?? 'ios'
      items.push({
        title: app.publisher.name,
        href: `/publishers/${pubPlatform}/${encodeURIComponent(app.publisher.external_id)}?name=${encodeURIComponent(app.publisher.name)}`,
      })
    } else if (app.publisher?.name) {
      items.push({ title: app.publisher.name })
    }
    items.push({ title: app.name ?? 'Loading...' })
    return items
  }

  if (path.startsWith('/apps/')) {
    return [
      { title: 'Apps', href: '/apps' },
      { title: 'Loading...' },
    ]
  }

  return []
}

interface NavItem {
  title: string
  href: string
  icon: React.ComponentType
  comingSoon?: boolean
}

const discoveryItems: NavItem[] = [
  { title: 'Trending', href: '/discovery/trending', icon: TrendingUp },
  { title: 'Apps', href: '/discovery/apps', icon: Compass },
  { title: 'Publishers', href: '/discovery/publishers', icon: Building2 },
]

const trackingItems: NavItem[] = [
  { title: 'Apps', href: '/apps', icon: Smartphone },
  { title: 'Competitors', href: '/competitors', icon: Users },
  { title: 'App Changes', href: '/changes/apps', icon: FileSearch },
  { title: 'Competitor Changes', href: '/changes/competitors', icon: FileSearch2 },
]

const asoItems: NavItem[] = [
  { title: 'Keyword Explorer', href: '#', icon: Key, comingSoon: true },
]

const explorerItems: NavItem[] = [
  { title: 'App Icons', href: '/explorer/icons', icon: Image },
  { title: 'Screenshots', href: '/explorer/screenshots', icon: Camera },
]

const apiItems: NavItem[] = [
  { title: 'API Keys', href: '#', icon: KeyRound, comingSoon: true },
  { title: 'MCP', href: '#', icon: Webhook, comingSoon: true },
  { title: 'API Docs', href: '#', icon: BookOpen, comingSoon: true },
]

function NavGroup({ label, items, pathname }: { label: string; items: NavItem[]; pathname: string }) {
  return (
    <SidebarGroup>
      <SidebarGroupLabel>{label}</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => (
          <SidebarMenuItem key={item.title}>
            {item.comingSoon ? (
              <SidebarMenuButton disabled className="opacity-50">
                <item.icon />
                <span>{item.title}</span>
                <Badge variant="outline" className="ml-auto text-[10px] px-1.5 py-0">Soon</Badge>
              </SidebarMenuButton>
            ) : (
              <SidebarMenuButton
                render={<Link to={item.href} />}
                isActive={
                  item.href === '/dashboard'
                    ? pathname === '/dashboard'
                    : pathname.startsWith(item.href)
                }
              >
                <item.icon />
                <span>{item.title}</span>
              </SidebarMenuButton>
            )}
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}

export default function AppLayout() {
  const location = useLocation()
  const app = useAppData()
  const breadcrumbs = useBreadcrumbs(app)
  usePageTitle(app)

  return (
    <SidebarProvider>
      <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton size="lg" render={<Link to="/apps" />}>
                <AppLogo />
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
          <NavGroup label="Discovery" items={discoveryItems} pathname={location.pathname} />
          <NavGroup label="Tracking" items={trackingItems} pathname={location.pathname} />
          <NavGroup label="ASO" items={asoItems} pathname={location.pathname} />
          <NavGroup label="Explorer" items={explorerItems} pathname={location.pathname} />
        </SidebarContent>
        <SidebarFooter>
          <NavGroup label="API" items={apiItems} pathname={location.pathname} />
          <NavUser />
          <div className="flex items-center justify-center gap-2 px-3 pb-2 group-data-[collapsible=icon]:hidden">
            <a
              href="https://github.com/appstorecat/appstorecat"
              target="_blank"
              rel="noopener noreferrer"
              className="text-muted-foreground/40 transition-colors hover:text-muted-foreground"
            >
              <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/></svg>
            </a>
            <span className="text-[10px] text-muted-foreground/40">
              AppStoreCat &copy; {new Date().getFullYear()} &mdash; v{__APP_VERSION__}
            </span>
          </div>
        </SidebarFooter>
        <SidebarRail />
      </Sidebar>
      <SidebarInset className="min-w-0">
        <header className="flex h-16 shrink-0 items-center gap-2 px-4">
          <SidebarTrigger className="-ml-1" />
          <Separator orientation="vertical" className="mr-2 h-4" />
          <Breadcrumbs items={breadcrumbs} />
        </header>
        <main className="flex min-w-0 flex-1 flex-col overflow-x-hidden">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  )
}
