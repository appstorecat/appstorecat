# Kimlik Dogrulama

AppStoreCat, token tabanli API kimlik dogrulamasi icin [Laravel Sanctum](https://laravel.com/docs/sanctum) kullanir.

## Kayit Ol

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

**Yanit (201):**

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

## Giris Yap

```
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "password123"
}
```

**Yanit (200):**

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

## Token Kullanimi

Korunan tum istekler icin token'i `Authorization` basligina ekleyin:

```
GET /api/v1/apps
Authorization: Bearer 2|def456...
```

## Cikis Yap

```
POST /api/v1/auth/logout
Authorization: Bearer 2|def456...
```

**Yanit (204):** Icerik yok. Token iptal edilir.

## Mevcut Kullanici

```
GET /api/v1/auth/me
Authorization: Bearer 2|def456...
```

**Yanit (200):**

```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com"
  }
}
```

## Oturum Yapilandirmasi

| Ayar | Varsayilan | Aciklama |
|------|------------|----------|
| `SESSION_DRIVER` | `database` | Oturum depolama surucusu |
| `SESSION_LIFETIME` | `120` | Dakika cinsinden oturum suresi |
| `BCRYPT_ROUNDS` | `12` | Parola hashleme turu sayisi |

## Guvenlik Notlari

- Token'larin varsayilan olarak suresi dolmaz. Iptal etmek icin acikca cikis yapin.
- Parolalar bcrypt (12 tur) ile hashlenir.
- Kimlik dogrulama endpoint'leri dakikada 5 istekle sinirlidir.
- Diger tum endpoint'ler dakikada 500 (yerel) veya dakikada 60 (production) istekle sinirlidir.
