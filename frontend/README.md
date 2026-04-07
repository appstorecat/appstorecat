# AppStoreCat Frontend

React single-page application for AppStoreCat.

## Tech Stack

- **React 19** with TypeScript
- **Vite 8** for bundling and dev server
- **shadcn/ui** component library
- **Tailwind CSS v4** for styling
- **Orval** for API client generation from OpenAPI spec

## Development

The frontend runs as a Docker container in the monorepo:

```bash
# From project root
make dev-frontend    # Start frontend only
make dev             # Start all services (includes frontend)
```

The dev server is available at http://localhost:7461.

## Pages

| Page | Description |
|------|-------------|
| **Apps** | List and manage tracked apps |
| **App Detail** | Full app view with listing, versions, reviews, keywords, competitors, changes |
| **Discovery > Apps** | Search for new apps |
| **Discovery > Publishers** | Search for publishers |
| **Discovery > Trending** | Browse trending charts |
| **Changes** | Store listing change timeline |
| **Competitors** | All competitor apps |
| **Explorer** | Browse screenshots and icons |
| **Publishers** | Publisher list and details |
| **Settings** | User profile and security |

## Documentation

- [Frontend Service Guide](../docs/services/frontend.md)
- [Full Documentation](../docs/)
