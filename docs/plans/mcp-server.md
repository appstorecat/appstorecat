# AppStoreCat MCP Server — Implementation Plan

> AppStoreCat API'sini MCP (Model Context Protocol) server olarak expose ederek Claude Code gibi AI araçlarının app intelligence verilerine erişmesini sağlamak.

## Architecture

```
Claude Code (stdio) → MCP Server (lokal) → Laravel API (local veya remote)
                                                  ↓
                                             auth:sanctum

Geliştirme:   MCP Server → http://localhost:7460/api/v1
Production:   MCP Server → https://server.appstore.cat/api/v1
```

- MCP Server, monorepo'da `mcp/` dizininde yaşar
- stdio transport ile çalışır (Claude Code, MCP server'ı lokal process olarak spawn eder)
- Laravel API'ye HTTP ile bağlanır, Sanctum bearer token kullanır
- **API her yerde olabilir** — `APPSTORECAT_API_URL` env var'ı ile belirlenir (lokal veya production)
- MCP server Docker container'da çalışmaz, sadece `node dist/index.js` olarak çalışır
- Sadece **read-only** tool'lar — write işlemi yok
- Kullanıcılar web UI'dan API token oluşturur, MCP server config'ine koyar

---

## Phase 1: Server-Side API Token Management

Laravel tarafında kişisel API token CRUD endpoint'leri.

### 1.1 Login Token Isolation Fix (CRITICAL)

**Dosya:** `server/app/Http/Controllers/Api/V1/Account/AuthController.php`

Mevcut login'de `$user->tokens()->delete()` TÜM token'ları siliyor — MCP token'ları dahil.

```php
// ÖNCE (tehlikeli — tüm token'ları siler):
$user->tokens()->delete();

// SONRA (güvenli — sadece auth-token'ları siler, MCP token'larına dokunmaz):
$user->tokens()->where('name', 'auth-token')->delete();
```

Bu değişiklik olmadan her login'de kullanıcının MCP token'ı ölür.

### 1.2 Token Controller

**Dosya:** `server/app/Http/Controllers/Api/V1/Account/ApiTokenController.php`

```
GET    /account/api-tokens     → index()   — Token listesi (id, name, last_used_at, created_at)
POST   /account/api-tokens     → store()   — Yeni token oluştur (plaintext sadece 1 kez döner)
DELETE /account/api-tokens/{id} → destroy() — Token sil
```

- Sanctum `$user->createToken($name, ['mcp'])` ile oluştur
- `abilities: ['mcp']` scope'u ile sınırla (ileride granüler kontrol için)
- `personal_access_tokens` tablosu zaten mevcut (migration var)
- Token adı zorunlu, max 255 karakter

### 1.3 Form Request

**Dosya:** `server/app/Http/Requests/Api/Account/StoreApiTokenRequest.php`

```php
'name' => ['required', 'string', 'max:255']
```

### 1.4 API Resource

**Dosya:** `server/app/Http/Resources/Api/Account/ApiTokenResource.php`

```php
return [
    'id' => $this->id,
    'name' => $this->name,
    'abilities' => $this->abilities,
    'last_used_at' => $this->last_used_at,
    'created_at' => $this->created_at,
];
```

### 1.5 Routes

**Dosya:** `server/routes/api.php` — protected routes bloğuna ekle:

```php
// API Tokens
Route::get('account/api-tokens', [V1\Account\ApiTokenController::class, 'index']);
Route::post('account/api-tokens', [V1\Account\ApiTokenController::class, 'store']);
Route::delete('account/api-tokens/{token}', [V1\Account\ApiTokenController::class, 'destroy']);
```

### 1.6 Swagger Annotations

Controller'a OA attributes ekle (mevcut AuthController pattern'ini kopyala).

### Verification

```bash
make test-server                      # Mevcut testler kırılmamalı
# Manuel test:
# POST /api/v1/account/api-tokens → plainTextToken döner
# GET /api/v1/account/api-tokens → token listesi
# DELETE /api/v1/account/api-tokens/{id} → 204
```

---

## Phase 2: Web Frontend — API Tokens Page

Web UI'da token yönetim sayfası.

### 2.1 API Client (Orval)

`make api` çalıştır → yeni endpoint'ler otomatik generate edilir.

### 2.2 API Tokens Page

**Dosya:** `web/src/pages/settings/ApiTokens.tsx`

Bileşenler:
- **Token listesi** — tablo: ad, oluşturma tarihi, son kullanım, sil butonu
- **Yeni token oluştur** — form: isim input + oluştur butonu
- **Token gösterme dialog** — oluşturulan token 1 kez gösterilir, kopyala butonu ile

UI pattern: `Settings.tsx`'teki Card/form yapısını kopyala.

### 2.3 Router

**Dosya:** `web/src/router.tsx`

```tsx
import ApiTokens from '@/pages/settings/ApiTokens'

// Account bölümüne ekle:
<Route path="/settings/api-tokens" element={<ApiTokens />} />
```

### 2.4 Sidebar Navigation

**Dosya:** `web/src/layouts/AppLayout.tsx`

`apiItems` dizisinde:
- "API Keys" → `href: '/settings/api-tokens'`, `comingSoon: false`
- "MCP" → `href: '/settings/mcp'`, `comingSoon: false` (Phase 4'te dolacak)

### Verification

```bash
make npm run build                    # Build başarılı
# Tarayıcıda /settings/api-tokens sayfasını aç
# Token oluştur → plaintext gösterilir
# Listeye eklenir
# Sil → listeden kalkar
```

---

## Phase 3: MCP Server Service

Monorepo'da yeni TypeScript MCP server servisi.

### 3.1 Proje Yapısı

```
mcp/
├── src/
│   ├── index.ts              # Entry point — server init + transport
│   ├── client.ts             # AppStoreCat API HTTP client
│   ├── tools/
│   │   ├── apps.ts           # search_apps, list_apps, get_app, get_app_listing
│   │   ├── competitors.ts    # get_app_competitors, list_all_competitors
│   │   ├── reviews.ts        # get_app_reviews, get_review_summary
│   │   ├── dashboard.ts      # get_dashboard
│   │   ├── charts.ts         # get_charts
│   │   ├── changes.ts        # get_app_changes, get_competitor_changes
│   │   ├── explorer.ts       # explore_screenshots, explore_icons
│   │   ├── publishers.ts     # search_publishers, list_publishers, get_publisher, get_publisher_apps
│   │   └── meta.ts           # list_countries, list_store_categories
│   └── register.ts           # Tüm tool'ları toplu register eder
├── package.json
├── tsconfig.json
└── README.md
```

### 3.2 package.json

```json
{
  "name": "@appstorecat/mcp",
  "version": "1.0.0",
  "type": "module",
  "bin": { "appstorecat-mcp": "./dist/index.js" },
  "scripts": {
    "dev": "tsx watch src/index.ts",
    "build": "tsc",
    "start": "node dist/index.js"
  },
  "dependencies": {
    "@modelcontextprotocol/sdk": "latest",
    "zod": "^3"
  },
  "devDependencies": {
    "@types/node": "^22",
    "tsx": "^4",
    "typescript": "^5"
  }
}
```

### 3.3 API Client (`client.ts`)

```typescript
// Basit HTTP client — fetch API kullanır (Node 18+ built-in)
// Config: APPSTORECAT_API_URL + APPSTORECAT_API_TOKEN env vars
// Her request'e Authorization: Bearer {token} header'ı ekler
// JSON response parse eder
// Hata durumunda { isError: true } MCP response döner
```

### 3.4 Server Entry Point (`index.ts`)

```typescript
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerAllTools } from './register.js';

const server = new McpServer({
  name: 'appstorecat',
  version: '1.0.0',
}, {
  instructions: 'AppStoreCat — App Store & Google Play intelligence toolkit. Search apps, track competitors, monitor changes, analyze reviews, explore trending charts.',
});

registerAllTools(server);

const transport = new StdioServerTransport();
await server.connect(transport);
```

### 3.5 Tool Definitions (20 tools)

Her tool şu pattern'i takip eder:

```typescript
server.registerTool(
  'tool_name',
  {
    description: 'What this tool does',
    inputSchema: z.object({ /* params */ }),
    annotations: { readOnlyHint: true },
  },
  async (args) => {
    const data = await client.get('/endpoint', args);
    return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
  }
);
```

#### Tool Listesi ve Input Schema'ları:

| Tool | Endpoint | Input Schema |
|------|----------|-------------|
| `get_dashboard` | GET /dashboard | `{}` |
| `search_apps` | GET /apps/search | `{ query: string, platform?: 'ios'\|'android' }` |
| `list_apps` | GET /apps | `{ platform?: 'ios'\|'android', page?: number }` |
| `get_app` | GET /apps/{p}/{id} | `{ platform: 'ios'\|'android', external_id: string }` |
| `get_app_listing` | GET /apps/{p}/{id}/listing | `{ platform: 'ios'\|'android', external_id: string }` |
| `get_app_competitors` | GET /apps/{p}/{id}/competitors | `{ platform: 'ios'\|'android', external_id: string }` |
| `get_app_reviews` | GET /apps/{p}/{id}/reviews | `{ platform: 'ios'\|'android', external_id: string, sort?: string, rating?: number }` |
| `get_review_summary` | GET /apps/{p}/{id}/reviews/summary | `{ platform: 'ios'\|'android', external_id: string }` |
| `list_all_competitors` | GET /competitors | `{}` |
| `get_app_changes` | GET /changes/apps | `{ field?: string }` |
| `get_competitor_changes` | GET /changes/competitors | `{ field?: string }` |
| `get_charts` | GET /charts | `{ platform?: 'ios'\|'android', country?: string, genre?: string }` |
| `explore_screenshots` | GET /explorer/screenshots | `{ platform?: 'ios'\|'android' }` |
| `explore_icons` | GET /explorer/icons | `{ platform?: 'ios'\|'android' }` |
| `list_countries` | GET /countries | `{}` |
| `list_store_categories` | GET /store-categories | `{}` |
| `search_publishers` | GET /publishers/search | `{ query: string, platform?: 'ios'\|'android' }` |
| `list_publishers` | GET /publishers | `{ page?: number }` |
| `get_publisher` | GET /publishers/{p}/{id} | `{ platform: 'ios'\|'android', external_id: string }` |
| `get_publisher_apps` | GET /publishers/{p}/{id}/store-apps | `{ platform: 'ios'\|'android', external_id: string }` |

### Verification

```bash
cd mcp && npm install && npm run build  # Build başarılı
# Manuel test — stdio ile:
echo '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | node dist/index.js
# 20 tool dönmeli
```

---

## Phase 4: Integration & Documentation

### 4.1 npm'e Publish

Paket `@appstorecat/mcp` adıyla npm'e publish edilir.

```bash
cd mcp && npm publish --access public
```

Kullanıcılar şu şekilde ekler:

```bash
# Claude Code'a ekle (npx ile — kurulum gerektirmez)
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=https://server.appstore.cat/api/v1 \
  -e APPSTORECAT_API_TOKEN=your-token \
  -- npx -y @appstorecat/mcp
```

### 4.2 Claude Code MCP Config (Manuel)

Alternatif olarak `.claude/settings.json` veya proje `.mcp.json` dosyasına elle:

```json
{
  "mcpServers": {
    "appstorecat": {
      "command": "npx",
      "args": ["-y", "@appstorecat/mcp"],
      "env": {
        "APPSTORECAT_API_URL": "https://server.appstore.cat/api/v1",
        "APPSTORECAT_API_TOKEN": "your-token-here"
      }
    }
  }
}
```

### 4.3 Web MCP Page

**Dosya:** `web/src/pages/settings/Mcp.tsx`

Kullanıcıya:
- MCP nedir açıklaması
- Claude Code config JSON'u (token otomatik doldurulmuş)
- Kopyala butonu
- Setup adımları

### 4.4 Makefile Targets

```makefile
# MCP
mcp-install:
	cd mcp && npm install

mcp-build:
	cd mcp && npm run build

mcp-dev:
	cd mcp && npm run dev
```

### 4.5 README Güncelleme

Ana README'ye MCP bölümü ekle.

### Verification

```bash
# End-to-end test:
# 1. Web'den token oluştur
# 2. MCP config'e token koy
# 3. Claude Code'da: "AppStoreCat'teki trending uygulamaları getir"
# 4. get_charts tool'u çalışır ve sonuç döner
```

---

## Implementation Order

```
Phase 1 → Phase 2 → Phase 3 → Phase 4
 Server     Web       MCP      Integration
 (token)   (token UI) (tools)  (docs + config)
```

Her phase bağımsız olarak test edilebilir. Phase 1 tamamlanmadan Phase 3'e başlanabilir (MCP server mock data ile test edilebilir), ama Phase 2 ve 4 sıraya bağlıdır.

## Anti-Pattern Guards

- **Token'ı MCP server'da hardcode etme** — her zaman env var
- **Write tool ekleme** — sadece read-only, `readOnlyHint: true` annotation
- **Sanctum auth-token'ı kullanma** — MCP için ayrı named token (`mcp` ability)
- **Tool'da büyük response döndürme** — gerekirse truncate/summarize et
- **Docker Compose'a MCP ekleme** — MCP server lokal çalışır (stdio), container'da değil