type GtagFn = (...args: unknown[]) => void

declare global {
  interface Window {
    dataLayer?: unknown[]
    gtag?: GtagFn
    __GA_MEASUREMENT_ID__?: string
  }
}

function getMeasurementId(): string | null {
  const id = window.__GA_MEASUREMENT_ID__
  return id && id.length > 0 ? id : null
}

const debug = import.meta.env.DEV

export function initAnalytics(): void {
  const id = getMeasurementId()
  if (!id) {
    if (debug) console.info('[analytics] disabled: no measurement id')
    return
  }
  if (window.gtag) return

  const script = document.createElement('script')
  script.async = true
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`
  document.head.appendChild(script)

  window.dataLayer = window.dataLayer || []
  const gtag: GtagFn = (...args) => {
    window.dataLayer!.push(args)
  }
  window.gtag = gtag

  gtag('js', new Date())
  gtag('config', id, { send_page_view: false })

  if (debug) console.info('[analytics] initialized:', id)
}

export function trackPageView(path: string): void {
  const id = getMeasurementId()
  if (!id || !window.gtag) return

  window.gtag('event', 'page_view', {
    page_path: path,
    page_location: window.location.href,
    page_title: document.title,
  })

  if (debug) console.info('[analytics] page_view:', path)
}
