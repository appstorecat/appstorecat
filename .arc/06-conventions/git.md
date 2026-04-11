# Git Conventions

## Commit Message Format

```
<type>: <description>
```

### Types
| Type | Usage |
|---|---|
| `add` | New feature or file |
| `fix` | Bug fix |
| `update` | Enhancement to existing feature |
| `remove` | Removing feature or file |
| `refactor` | Code restructuring (no behavior change) |
| `chore` | Build, deps, config changes |

### Rules
- Imperative mood: "add connector" not "added connector"
- Under 50 characters
- Single line, no description body
- English only
- No emoji
- **NEVER** add Co-Authored-By or AI attribution
- Stage specific files, never `git add .` or `git add -A`

### Examples
```
add: iTunes lookup connector
fix: rating calculation for zero reviews
update: listing view with version history
refactor: extract DNA build to service
remove: unused team middleware
chore: update composer dependencies
```

## Before Committing
1. `./vendor/bin/sail php ./vendor/bin/pint` — format code
2. `./vendor/bin/sail test` — run tests
3. Review changes with `git diff`
