# UI Components

## Component Library: shadcn/ui (React)

The web app is a separate React 19 SPA in the `web/` directory. UI components use shadcn/ui (React).

### Available UI Components (`web/src/components/ui/`)

alert, avatar, badge, breadcrumb, button, card, checkbox, collapsible, dialog, dropdown-menu, input, input-otp, label, navigation-menu, select, separator, sheet, sidebar, skeleton, spinner, tabs, tooltip

### Adding New UI Components

```bash
cd web && npx shadcn@latest add <component-name>
```

Components are installed to `web/src/components/ui/`.

## Component Patterns

### Function Components Only
All components are React 19 function components with TypeScript.

```tsx
interface AppCardProps {
    app: App;
}

export function AppCard({ app }: AppCardProps) {
    return (
        <Card className="cursor-pointer transition-colors hover:border-primary">
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{app.name}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-sm text-muted-foreground">{app.platform}</p>
            </CardContent>
        </Card>
    );
}
```

### Button Variants
Defined via `class-variance-authority` (CVA):

| Variant | Usage |
|---------|-------|
| `default` | Primary actions (bg-primary) |
| `destructive` | Dangerous actions (bg-destructive) |
| `outline` | Secondary actions with border |
| `secondary` | Subtle secondary actions |
| `ghost` | Minimal, hover-only appearance |
| `link` | Text link style with underline |

Sizes: `default` (h-9), `sm` (h-8), `lg` (h-10), `icon` (size-9)

### Dialog (Modal) Pattern
```tsx
<Dialog open={open} onOpenChange={setOpen}>
    <DialogTrigger render={<Button>Open</Button>} />
    <DialogContent>
        <DialogHeader>
            <DialogTitle>Title</DialogTitle>
            <DialogDescription>Description</DialogDescription>
        </DialogHeader>
        {/* content */}
        <DialogFooter>
            <Button type="submit">Save</Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
```

### Composition: `render` prop
Use the `render` prop (not `asChild`) for component composition. This is the shadcn v4 / base-ui pattern.

```tsx
<DialogTrigger render={<Button variant="outline">Open</Button>} />
<NavigationMenuLink render={<Link to="/apps">Apps</Link>} />
```

### Badge Variants
```tsx
<Badge variant="default">completed</Badge>
<Badge variant="destructive">failed</Badge>
<Badge variant="secondary">pending</Badge>
<Badge variant="outline">iOS</Badge>
```

### Data Fetching with TanStack Query
```tsx
import { useQuery } from '@tanstack/react-query';
import { getApps } from '@/api/generated';

export function AppsList() {
    const { data, isLoading } = useQuery({
        queryKey: ['apps'],
        queryFn: () => getApps(),
    });

    if (isLoading) return <Skeleton className="h-32" />;

    return data?.map((app) => <AppCard key={app.id} app={app} />);
}
```

### Auth State with Zustand
```tsx
import { useAuthStore } from '@/stores/auth';

export function UserMenu() {
    const { user, logout } = useAuthStore();
    // ...
}
```

## Layout Structure

- Sidebar layout with `SidebarProvider` pattern
- `AppLayout` wraps authenticated pages with sidebar
- `AuthLayout` wraps login/register pages
- React Router for page navigation

## Spacing Conventions
- Page padding: `p-4`
- Section gaps: `gap-6`
- Grid gaps: `gap-4`
- Form spacing: `space-y-4` (fields), `space-y-2` (label+input)
- Content spacing: `space-y-3`

## Icons

- **Library:** lucide-react
- **Import:** `import { RefreshCw, Trash2 } from 'lucide-react'`
- **Sizes:** `className="h-4 w-4"` (inline), `className="h-12 w-12"` (empty states)

## DO / DON'T
- DO: Use existing shadcn/ui components from `ui/` before creating custom ones
- DO: Import components from their barrel: `import { Button } from '@/components/ui/button'`
- DO: Use TanStack Query for all API data fetching
- DO: Use `render` prop for composition (not `asChild`)
- DON'T: Create custom components for things shadcn/ui already provides
- DON'T: Use raw HTML form elements — use shadcn/ui Input, Select, Label
- DON'T: Use class components
- DON'T: Fetch data with useEffect + useState (use TanStack Query)
