import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom'
import { useAuthStore } from '@/stores/auth'
import { useAnalyticsPageViews } from '@/hooks/useAnalyticsPageViews'
import AppLayout from '@/layouts/AppLayout'
import Landing from '@/pages/Landing'
import Login from '@/pages/auth/Login'
import Register from '@/pages/auth/Register'
import AppsIndex from '@/pages/apps/Index'
import AppsShow from '@/pages/apps/Show'
import CompetitorsIndex from '@/pages/competitors/Index'
import Settings from '@/pages/Settings'
import ApiTokens from '@/pages/settings/ApiTokens'
import McpSetup from '@/pages/settings/Mcp'
import PublishersIndex from '@/pages/publishers/Index'
import PublishersShow from '@/pages/publishers/Show'
import AppChanges from '@/pages/changes/AppChanges'
import CompetitorChanges from '@/pages/changes/CompetitorChanges'
import DiscoveryApps from '@/pages/discovery/Apps'
import DiscoveryPublishers from '@/pages/discovery/Publishers'
import Trending from '@/pages/discovery/Trending'
import ExplorerScreenshots from '@/pages/explorer/Screenshots'
import ExplorerIcons from '@/pages/explorer/Icons'

function AuthGuard() {
  const { token, isLoading } = useAuthStore()

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center">
        <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
      </div>
    )
  }

  if (!token) {
    return <Navigate to="/" replace />
  }

  return <Outlet />
}

function GuestGuard() {
  const { token, isLoading } = useAuthStore()

  if (isLoading) return null

  if (token) {
    return <Navigate to="/discovery/trending" replace />
  }

  return <Outlet />
}

function AnalyticsTracker() {
  useAnalyticsPageViews()
  return null
}

export default function Router() {
  return (
    <BrowserRouter>
      <AnalyticsTracker />
      <Routes>
        <Route path="/" element={<Landing />} />
        <Route element={<GuestGuard />}>
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
        </Route>

        <Route element={<AuthGuard />}>
          <Route element={<AppLayout />}>
            {/* Discovery */}
            <Route path="/discovery/apps" element={<DiscoveryApps />} />
            <Route path="/discovery/publishers" element={<DiscoveryPublishers />} />
            <Route path="/discovery/trending" element={<Trending />} />

            {/* Tracking */}
            <Route path="/apps" element={<AppsIndex />} />
            <Route path="/apps/:platform/:externalId" element={<AppsShow />} />
            <Route path="/competitors" element={<CompetitorsIndex />} />
            <Route path="/changes/apps" element={<AppChanges />} />
            <Route path="/changes/competitors" element={<CompetitorChanges />} />

            {/* Explorer */}
            <Route path="/explorer/screenshots" element={<ExplorerScreenshots />} />
            <Route path="/explorer/icons" element={<ExplorerIcons />} />

            {/* Publishers */}
            <Route path="/publishers" element={<PublishersIndex />} />
            <Route path="/publishers/:platform/:externalId" element={<PublishersShow />} />

            {/* Account */}
            <Route path="/settings" element={<Settings />} />
            <Route path="/settings/api-tokens" element={<ApiTokens />} />
            <Route path="/settings/mcp" element={<McpSetup />} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  )
}
