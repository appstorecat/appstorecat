# MCP Server

AppStoreCat MCP (Model Context Protocol) server, Claude Code gibi AI araçlarının uygulama zekası verilerine doğrudan erişmesini sağlar.

## Genel Bakış

| | |
|---|---|
| **Paket** | npm'de `@appstorecat/mcp` |
| **Transport** | stdio (lokal process olarak spawn edilir) |
| **Auth** | Env var üzerinden Sanctum bearer token |
| **Tool'lar** | 19 read-only tool |

```
Claude Code (stdio) → MCP Server (lokal) → Laravel API (lokal veya remote)
                                                  ↓
                                             auth:sanctum
```

MCP server Docker'da **çalışmaz**. Claude Code'un stdio üzerinden spawn ettiği lokal bir Node.js process'idir.

## Kurulum

### Seçenek A: Claude Code CLI

```bash
claude mcp add appstorecat \
  -e APPSTORECAT_API_URL=https://server.appstore.cat/api/v1 \
  -e APPSTORECAT_API_TOKEN=your-token \
  -- npx -y @appstorecat/mcp
```

### Seçenek B: Manuel Config

`.claude/settings.json` veya proje `.mcp.json` dosyasına ekle:

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

## Ortam Değişkenleri

| Değişken | Zorunlu | Varsayılan | Açıklama |
|----------|---------|------------|----------|
| `APPSTORECAT_API_TOKEN` | Evet | — | Sanctum API token (web UI'dan oluşturulur) |
| `APPSTORECAT_API_URL` | Hayır | `http://localhost:7460/api/v1` | API base URL |

## Mevcut Tool'lar

### Keşif

| Tool | Açıklama |
|------|----------|
| `search_apps` | App Store veya Google Play'de anahtar kelimeyle uygulama ara |
| `search_publishers` | Yayıncı/geliştirici ara |
| `get_charts` | Trend/top listelerini getir (top_free, top_paid, top_grossing) |

### Uygulamalar

| Tool | Açıklama |
|------|----------|
| `list_apps` | Takip edilen tüm uygulamaları listele |
| `get_app` | Detaylı uygulama bilgisi (metadata, puanlar, versiyon geçmişi) |
| `get_app_listing` | Mağaza listesini getir (açıklama, ekran görüntüleri, yenilikler) |

### Rakipler

| Tool | Açıklama |
|------|----------|
| `get_app_competitors` | Belirli bir uygulamanın rakiplerini getir |
| `list_all_competitors` | Tüm rakip ilişkilerini listele |

### Değerlendirmeler

| Tool | Açıklama |
|------|----------|
| `get_app_reviews` | Kullanıcı yorumlarını getir (puana göre filtre, sıralama) |
| `get_review_summary` | Yorum özeti (puan dağılımı, ortalamalar) |

### Değişiklikler

| Tool | Açıklama |
|------|----------|
| `get_app_changes` | Takip edilen uygulamaların son mağaza değişiklikleri |
| `get_competitor_changes` | Rakip uygulamaların son değişiklikleri |

### Gezgin

| Tool | Açıklama |
|------|----------|
| `explore_screenshots` | Takip edilen uygulamaların ekran görüntülerini incele |
| `explore_icons` | Takip edilen uygulamaların ikonlarını incele |

### Yayıncılar

| Tool | Açıklama |
|------|----------|
| `list_publishers` | Bilinen yayıncıları uygulama sayılarıyla listele |
| `get_publisher` | Yayıncı detaylarını getir |
| `get_publisher_apps` | Bir yayıncının tüm uygulamalarını getir |

### Meta

| Tool | Açıklama |
|------|----------|
| `list_countries` | Desteklenen ülkeleri/bölgeleri listele |
| `list_store_categories` | Tüm uygulama mağazası kategorilerini listele |
| `get_dashboard` | Dashboard özeti (uygulama sayısı, son değişiklikler/yorumlar) |

## Geliştirme

```bash
make mcp-install   # Bağımlılıkları kur
make mcp-build     # TypeScript derle
make mcp-dev       # Dev modunda çalıştır (tsx watch)
```

### Proje Yapısı

```
mcp/
├── src/
│   ├── index.ts          # Giriş noktası — server init + stdio transport
│   ├── client.ts         # HTTP client (fetch + bearer auth)
│   ├── register.ts       # Tüm tool modüllerini register eder
│   └── tools/
│       ├── apps.ts       # search_apps, list_apps, get_app, get_app_listing
│       ├── charts.ts     # get_charts
│       ├── changes.ts    # get_app_changes, get_competitor_changes
│       ├── competitors.ts # get_app_competitors, list_all_competitors
│       ├── dashboard.ts  # get_dashboard
│       ├── explorer.ts   # explore_screenshots, explore_icons
│       ├── meta.ts       # list_countries, list_store_categories
│       ├── publishers.ts # search/list/get publishers
│       └── reviews.ts    # get_app_reviews, get_review_summary
├── package.json
└── tsconfig.json
```

## Yayınlama

MCP paketi release pipeline'ına dahildir:

```bash
make release v=1.0.2
# → Docker image'ları build eder
# → mcp/package.json versiyonunu günceller
# → npm publish @appstorecat/mcp
# → git tag + push
```
