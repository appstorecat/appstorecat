import { useEffect, useRef, type ReactNode } from 'react'

interface ScrollableRowProps {
  children: ReactNode
  className?: string
  activeKey?: string
}

export default function ScrollableRow({ children, className, activeKey }: ScrollableRowProps) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!ref.current || !activeKey) return
    const active = ref.current.querySelector<HTMLElement>(`[data-scroll-key="${activeKey}"]`)
    if (!active) return
    active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' })
  }, [activeKey])

  return (
    <div
      ref={ref}
      className={`-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0 [&>*]:shrink-0 ${className ?? ''}`}
    >
      {children}
    </div>
  )
}
