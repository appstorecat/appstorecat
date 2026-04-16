# Design System

## Theming: shadcn/ui (React)

This project uses **shadcn/ui** with `neutral` base color. All colors are CSS variables with light/dark mode support.

### CSS Variables (defined in `web/src/index.css`)

```
:root / .dark {
    --background, --foreground
    --card, --card-foreground
    --popover, --popover-foreground
    --primary, --primary-foreground
    --secondary, --secondary-foreground
    --muted, --muted-foreground
    --accent, --accent-foreground
    --destructive, --destructive-foreground
    --border, --input, --ring
    --chart-1..5
    --sidebar-*, --radius
}
```

### Color Usage in Tailwind
Use semantic color tokens, never raw hex/hsl values:

```html
<!-- CORRECT -->
<div className="bg-background text-foreground">
<div className="bg-card text-card-foreground">
<div className="bg-muted text-muted-foreground">
<div className="border-border">
<div className="bg-destructive text-white">

<!-- WRONG -->
<div className="bg-gray-900 text-white">
<div className="bg-[#0d1117]">
```

### Dark Mode
- Toggle via `.dark` class on root element
- Use `dark:` prefix for dark-mode overrides when needed
- Most components handle dark mode automatically via CSS variables

## Typography

- **Font:** Geist Variable (`--font-sans`), from shadcn init
- **Fallback:** ui-sans-serif, system-ui, sans-serif
- **Monospace:** Use `font-mono` class for store IDs, technical values

## Icons

- **Library:** lucide-react
- **Sizes:** `className="h-4 w-4"` (inline), `className="h-12 w-12"` (empty states)
- Import individually: `import { RefreshCw, Trash2 } from 'lucide-react'`

## DO / DON'T
- DO: Use CSS variable-based color tokens (`bg-primary`, `text-muted-foreground`)
- DO: Support both light and dark modes
- DO: Use `font-mono` for technical/code values
- DON'T: Hardcode hex/hsl color values
- DON'T: Use custom color classes outside the CSS variable system
- DON'T: Import entire icon libraries
