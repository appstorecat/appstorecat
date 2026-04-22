import { useEffect, useState } from 'react'

type RGB = { r: number; g: number; b: number }

function sampleDominant(img: HTMLImageElement): RGB | null {
  const size = 16
  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const ctx = canvas.getContext('2d', { willReadFrequently: true })
  if (!ctx) return null
  try {
    ctx.drawImage(img, 0, 0, size, size)
    const { data } = ctx.getImageData(0, 0, size, size)
    let r = 0
    let g = 0
    let b = 0
    let count = 0
    for (let i = 0; i < data.length; i += 4) {
      const alpha = data[i + 3]
      if (alpha < 200) continue
      r += data[i]
      g += data[i + 1]
      b += data[i + 2]
      count++
    }
    if (count === 0) return null
    r = Math.round(r / count)
    g = Math.round(g / count)
    b = Math.round(b / count)

    // Boost saturation slightly so pastel icons still produce a visible tint.
    const max = Math.max(r, g, b)
    const min = Math.min(r, g, b)
    if (max - min < 30) {
      // Near-grayscale, bias toward a neutral warm tone instead of dull gray.
      return { r: Math.min(255, r + 30), g, b: Math.max(0, b - 10) }
    }
    return { r, g, b }
  } catch {
    // Likely CORS (tainted canvas); caller falls back to null.
    return null
  }
}

export function useDominantColor(src: string | null | undefined): RGB | null {
  const [color, setColor] = useState<RGB | null>(null)

  useEffect(() => {
    setColor(null)
    if (!src) return
    const img = new Image()
    img.crossOrigin = 'anonymous'
    img.referrerPolicy = 'no-referrer'
    img.onload = () => {
      const c = sampleDominant(img)
      if (c) setColor(c)
    }
    img.src = src
  }, [src])

  return color
}

export function rgbToCss({ r, g, b }: RGB, alpha = 1): string {
  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}
