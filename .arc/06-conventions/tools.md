# AI Tools & MCP Usage

## Laravel Boost MCP

This project has Laravel Boost MCP server configured. Use its tools:

### Documentation Search
- **ALWAYS** use `search-docs` tool before making code changes for Laravel ecosystem packages
- Pass multiple broad queries: `['rate limiting', 'routing']`
- Do NOT add package names to queries — package info is shared automatically
- Search docs for: Laravel, Inertia, Tailwind, Pest, Sanctum, Fortify, etc.

### Database
- Use `database-query` for read-only DB queries
- Use `database-schema` to inspect table structures
- Use `tinker` for executing PHP/Eloquent for debugging

### Debugging
- Use `browser-logs` to read browser errors and exceptions
- Use `last-error` to get the most recent application error
- Use `read-log-entries` for Laravel log inspection

### Application Info
- Use `application-info` for project metadata
- Use `get-absolute-url` when sharing URLs with the user
- Use `database-connections` to check DB connection status

### Artisan
- Use `list-artisan-commands` to verify command parameters before running

## When to Use Boost vs Direct Commands

| Task | Use |
|------|-----|
| Check documentation | `search-docs` tool |
| Read DB data | `database-query` tool |
| Inspect schema | `database-schema` tool |
| Debug PHP | `tinker` tool |
| Run tests | `./vendor/bin/sail test` (Bash) |
| Format code | `./vendor/bin/sail php ./vendor/bin/pint` (Bash) |
| Generate files | `./vendor/bin/sail artisan make:*` (Bash) |
