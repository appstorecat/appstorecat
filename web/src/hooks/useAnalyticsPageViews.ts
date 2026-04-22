import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { trackPageView } from '@/lib/analytics'

export function useAnalyticsPageViews(): void {
  const location = useLocation()

  useEffect(() => {
    trackPageView(location.pathname + location.search)
  }, [location.pathname, location.search])
}
