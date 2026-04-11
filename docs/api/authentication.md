# Authentication

AppStoreCat uses [Laravel Sanctum](https://laravel.com/docs/sanctum) for token-based API authentication.

## Register

```
POST /api/v1/auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201):**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "1|abc123..."
  }
}
```

## Login

```
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Response (200):**

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "token": "2|def456..."
  }
}
```

## Using the Token

Include the token in the `Authorization` header for all protected requests:

```
GET /api/v1/apps
Authorization: Bearer 2|def456...
```

## Logout

```
POST /api/v1/auth/logout
Authorization: Bearer 2|def456...
```

**Response (204):** No content. The token is revoked.

## Current User

```
GET /api/v1/auth/me
Authorization: Bearer 2|def456...
```

**Response (200):**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

## Session Configuration

| Setting | Default | Description |
|---------|---------|-------------|
| `SESSION_DRIVER` | `database` | Session storage driver |
| `SESSION_LIFETIME` | `120` | Session lifetime in minutes |
| `BCRYPT_ROUNDS` | `12` | Password hashing rounds |

## Security Notes

- Tokens do not expire by default. Logout explicitly to revoke.
- Passwords are hashed with bcrypt (12 rounds).
- Auth endpoints are rate-limited to 5 requests/minute.
- All other endpoints are rate-limited to 500/minute (local) or 60/minute (production).
