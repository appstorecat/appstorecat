# MCP Server

AppStoreCat MCP (Model Context Protocol) server, Claude Code gibi AI araçlarının uygulama zekası verilerine doğrudan erişmesini sağlar.

## Genel Bakış

| | |
|---|---|
| **Paket** | npm'de `@appstorecat/mcp` |
| **Transport** | stdio (lokal process olarak spawn edilir) |
| **Auth** | Env var üzerinden Sanctum bearer token |
| **Tool'lar** | 25 read-only tool, Swagger-strict, chain-first |

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

## Tasarım İlkeleri

Her tool iki kuralı schema seviyesinde zorunlu kılar:

- **Swagger-strict** — her tool'un zod girdisi Swagger parametre listesini birebir yansıtır. Uydurma alan yok, eksik alan yok. Integer alanlar string kabul etmez, enum'lar serbest metin kabul etmez, tarihler `YYYY-MM-DD` formatını dayatır.
- **Chain-first** — cevaplar olduğu gibi geçilir (`app_id`, `external_id`, `version_id`, `category_id`, `publisher.external_id` asla soyulmaz). Her tool açıklaması "use with: {tool_a}, {tool_b}" ipucuyla biter; çağıran çok adımlı aramaları planlayabilir.

## Mevcut Tool'lar

### Referans

| Tool | Açıklama |
|------|----------|
| `list_categories` | Mağaza kategorileri (App Store + Google Play). `category_id` → `get_charts`, `browse_screenshots`, `browse_icons`. |
| `list_countries` | Desteklenen ülkeler (ISO-2 kodları). `country_code` → lokasyon duyarlı tüm tool'lar. |

### Uygulamalar

| Tool | Açıklama |
|------|----------|
| `list_tracked_apps` | Kullanıcının takip ettiği uygulamalar. Zincirleme için iç `id` ve `{platform, external_id}` döner. |
| `search_store_apps` | Mağaza içi anahtar kelime araması (`term`, `platform`, `country_code`, `exclude_external_ids[]`). |
| `get_app` | Tam uygulama metadata'sı: yayıncı, kategori, versiyonlar, puan, `unavailable_countries`, (izleniyorsa) rakipler. |
| `get_app_listing` | Belirli `country_code` + `locale` için mağaza listesi (başlık, alt başlık, açıklama, ekran görüntüleri, whats_new, `version_id`). |
| `get_app_sync_status` | Uygulamanın sync pipeline durumu. |
| `get_app_rankings` | Chart sıralamaları (`date`, `collection` filtrelenebilir). |

### Rakipler

| Tool | Açıklama |
|------|----------|
| `list_app_competitors` | Belirli bir uygulamanın rakipleri. |
| `list_all_competitors` | Tüm izlenen rakip grupları `{parent, competitors[]}`. |

### Değişiklikler

| Tool | Açıklama |
|------|----------|
| `list_app_changes` | İzlenen uygulamaların listing değişiklikleri (`field`, `platform`, `search`, iç `app_id`; sayfalı). |
| `list_competitor_changes` | Aynı şema, rakip uygulamalarla sınırlı. |

### Chart'lar

| Tool | Açıklama |
|------|----------|
| `get_charts` | Top chart'lar (`top_free` / `top_paid` / `top_grossing`) — `country_code`, opsiyonel `category_id`. |

### Puanlar

| Tool | Açıklama |
|------|----------|
| `get_rating_summary` | Anlık puan + oy sayısı + histogram. |
| `get_rating_history` | Günlük puan serisi (`days` 1–90, varsayılan 30). |
| `get_rating_country_breakdown` | Ülkeye göre puan dağılımı (yalnız iOS). |

### Anahtar Kelimeler

| Tool | Açıklama |
|------|----------|
| `get_app_keywords` | Bir listing'in anahtar kelime yoğunluğu (`locale`, `ngram` 1–3, `sort`, `order`, `per_page`, `page`; opsiyonel `version_id`). |
| `compare_app_keywords` | En fazla 5 uygulama arasında karşılaştırmalı anahtar kelime tablosu (`app_ids[]` iç id'ler, `version_ids` map). |

### Yayıncılar

| Tool | Açıklama |
|------|----------|
| `search_publishers` | Mağazalar arası yayıncı araması (`term`, `platform`, `country_code`). |
| `list_user_publishers` | Kullanıcının takip ettiği uygulamaların yayıncıları. |
| `get_publisher` | Yayıncı detayı + caller'a ait takip edilen uygulamalar. |
| `get_publisher_store_apps` | Yayıncının mağaza kataloğunun tamamı (uygulama başına `is_tracked` bayrağı ile). |

### Gezgin

| Tool | Açıklama |
|------|----------|
| `browse_screenshots` | İzlenen uygulamalar genelinde sayfalı ekran görüntüsü akışı (`platform`, `category_id`, `search` filtreleri). |
| `browse_icons` | İzlenen uygulamalar genelinde sayfalı ikon akışı. |

### Dashboard

| Tool | Açıklama |
|------|----------|
| `get_dashboard` | Dashboard özeti — uygulama sayıları, son değişiklikler. `recent_changes[].app_id` → `list_app_changes`. |

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
│   ├── client.ts         # HTTP client (fetch + bearer auth, array/object query serializer'ları, path builder)
│   ├── register.ts       # Tüm tool modüllerini register eder
│   └── tools/
│       ├── _schemas.ts      # Ortak zod ilkelleri (Platform, ExternalId, DateStr, Ngram, …)
│       ├── apps.ts          # list_tracked_apps, search_store_apps, get_app, get_app_listing, get_app_sync_status, get_app_rankings
│       ├── changes.ts       # list_app_changes, list_competitor_changes
│       ├── charts.ts        # get_charts
│       ├── competitors.ts   # list_app_competitors, list_all_competitors
│       ├── dashboard.ts     # get_dashboard
│       ├── explorer.ts      # browse_screenshots, browse_icons
│       ├── keywords.ts      # get_app_keywords, compare_app_keywords
│       ├── publishers.ts    # search_publishers, list_user_publishers, get_publisher, get_publisher_store_apps
│       ├── ratings.ts       # get_rating_summary, get_rating_history, get_rating_country_breakdown
│       └── reference.ts     # list_categories, list_countries
├── package.json
└── tsconfig.json
```

## Yayınlama

MCP paketi release pipeline'ına dahildir:

```bash
make release v=1.2.0
# → Docker image'ları build eder
# → mcp/package.json versiyonunu günceller
# → npm publish @appstorecat/mcp
# → git tag + push
```
