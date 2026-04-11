# Frontend Conventions

## Stack
- React 19 with TypeScript
- Vite for bundling
- Tailwind CSS v4 (CSS-first config)
- shadcn/ui (React) for UI components
- TanStack Query for server state (API data fetching)
- Zustand for client state (auth store)
- React Router for navigation
- Orval for API client generation from Swagger JSON

## Project Location
Frontend lives in `frontend/` directory, runs on port 7461.

## Component Pattern

```tsx
interface AppCardProps {
    app: App;
}

export function AppCard({ app }: AppCardProps) {
    return <div>{app.name}</div>;
}
```

## Rules
- ALWAYS use function components (no class components)
- ALWAYS use TypeScript
- PascalCase for component file names
- camelCase for variables and functions
- Use shadcn/ui components before creating custom ones

## Data Fetching: TanStack Query
Use `useQuery` for reads and `useMutation` for writes. Never use `useEffect` + `useState` for API calls.

```tsx
import { useQuery, useMutation } from '@tanstack/react-query';

// Read
const { data, isLoading } = useQuery({
    queryKey: ['apps'],
    queryFn: () => getApps(),
});

// Write
const mutation = useMutation({
    mutationFn: (data: StoreAppRequest) => storeApp(data),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['apps'] }),
});
```

## Global State: Zustand
Use Zustand for auth state and other client-side state.

```tsx
import { create } from 'zustand';

interface AuthState {
    user: User | null;
    token: string | null;
    setAuth: (user: User, token: string) => void;
    logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
    user: null,
    token: null,
    setAuth: (user, token) => set({ user, token }),
    logout: () => set({ user: null, token: null }),
}));
```

## API Client: Orval
Orval generates typed API hooks and functions from the Swagger JSON.

```bash
cd frontend && npm run api:generate
```

Generated files live in `frontend/src/api/generated/`. Do not edit generated files manually.

## Navigation: React Router
```tsx
import { Link, useNavigate } from 'react-router-dom';

<Link to="/apps">Apps</Link>

const navigate = useNavigate();
navigate('/apps');
```

## Tailwind CSS v4
- Use `@import "tailwindcss"` (not `@tailwind` directives)
- All colors via CSS variables (`bg-primary`, `text-muted-foreground`)
- No deprecated utilities (see Tailwind v4 migration guide)

## DO / DON'T
- DO: Check for existing shadcn/ui components before creating custom ones
- DO: Use Tailwind utility classes, no custom CSS
- DO: Follow `.arc/03-ui/` for design system and component patterns
- DO: Use TanStack Query for API calls
- DO: Use Orval-generated client (never hand-write fetch calls)
- DON'T: Use class components
- DON'T: Use `useEffect` + `useState` for data fetching
- DON'T: Use `@tailwind` directives (v3 syntax)
- DON'T: Hardcode colors — use CSS variable tokens
- DON'T: Edit Orval-generated files manually
