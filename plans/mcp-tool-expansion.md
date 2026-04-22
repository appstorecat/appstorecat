# MCP Tool Genişletme Planı (Chain-First + Swagger-Strict)

## Amaç

`@appstorecat/mcp` paketi şu anda tek tool expose ediyor (`get_categories`). Bu plan, MCP sunucusunu **Swagger'a birebir sadık** ve **tool chaining** prensibiyle genişletir.

## Temel Kurallar (Hepsi Bağlayıcı)

### 1. Swagger-Strict (Yeni — kullanıcı talebi)

- **Hiçbir tool Swagger'da tanımlı olmayan bir parametre kabul etmez.** Uydurulmuş alan yok. Örn. `get_publisher`'a `country_code` eklenmez çünkü Swagger böyle bir alan tanımlamıyor.
- **Swagger'da tanımlı her parametre tool'a eklenir.** Eksik alan yok. Örn. `get_publisher`'ın `name` query param'ı plana alınacak.
- **Type casting birebir Swagger şemasına uyar:**
  - `integer` → zod `z.number().int()` — string kabul edilmez
  - `string` + `enum` → `z.enum([...])` — literal union
  - `array` + `items=string` → `z.array(z.string())`
  - `array` + `items=integer` → `z.array(z.number().int())`
  - `object` (ör. `version_ids`) → `z.record(z.string(), z.number().int())` veya Swagger'ın tarif ettiği map şekli
  - `format=date` → `z.string().regex(/^\d{4}-\d{2}-\d{2}$/)`
  - `min`/`max`/`default` → zod `.min() / .max() / .default()`
- **Required ↔ optional:** Swagger'da `required: true` olan parametre zod'da `.optional()` yazılmaz; aksi halde `.optional()`.
- **Default değerler** Swagger'da varsa zod şemasına yansıtılır (örn. `per_page` default 100) — ama client'tan boş gönderirsek API kendi default'unu uyguladığı için zod tarafında `.optional()` + `.default()` olarak işlenir ve `undefined` ise query string'e eklenmez.

### 2. Chain-First

- **Kimlikler asla gizlenmez.** Her response'ta `id`, `external_id`, `platform`, `category_id`, `publisher.external_id`, `version_id` ham haliyle tutulur.
- **Tool açıklamaları chain ipuçları içerir.** Her `description` alanı "Returns: … — use with: {tool_a}, {tool_b}" bloğu ile biter.
- **ID türü ayrımı açık:** `app_id` = internal integer id (`list_tracked_apps` → `id`); `external_id` = store id (string). Her tool açıklamasında hangisinin istendiği net.
- **Enum/ID upstream tool'dan beslenir:** `category_id` için `list_categories`, `country_code` için `list_countries`, `version_id` için `get_app_listing` → açıklamalarda linklenir.
- **Response projection yok:** Server'dan dönen JSON olduğu gibi `JSON.stringify` ile iletilir; chain için gereken alanlar kaybolmaz.

### 3. Read-Only

Tüm mutasyon endpoint'leri kapsam dışı: `POST/PUT/PATCH/DELETE` hiçbiri, `/auth/*`, `/account/*` hepsi.

## Faz 0 — Client Ön-Gereksinimleri

`src/client.ts` aşağıdakileri destekleyecek şekilde güncellenir. Bunlar olmadan Faz 1+ tool'ları çalışmaz.

1. **Array query param** (`exclude_external_ids[]`, `app_ids[]`):
   ```ts
   if (Array.isArray(value)) {
     for (const v of value) url.searchParams.append(`${key}[]`, String(v));
   }
   ```
   `app_ids[]=1&app_ids[]=2&app_ids[]=3` formatında.

2. **Object query param** (`version_ids`):
   ```ts
   if (value && typeof value === 'object' && !Array.isArray(value)) {
     for (const [k, v] of Object.entries(value)) url.searchParams.set(`${key}[${k}]`, String(v));
   }
   ```
   `version_ids[1]=99&version_ids[2]=102` formatında. Laravel `Request->input('version_ids')` bunu assoc array olarak okur.

3. **Integer tip korunumu:** zod validation sonrası `number` tipinde gelen değer `String(value)` ile query'ye yazılır; API tarafı string de kabul ediyor (Laravel casting). Bu aşamada özel bir şey gerekmez, ama zod giriş validasyonu integer'ı garantiler (string kabul etmez).

4. **Undefined filtreleme:** Mevcut davranış zaten doğru — `value !== undefined` kontrolü var. Optional + `.default()` olan zod alanlar, kullanıcı geçmezse `undefined` kalır ve query'ye eklenmez (API kendi default'unu uygular). Bu bilinçli tercih — server default'u ile MCP default'u tek kaynakta (Laravel) kalsın.

5. **Hata normalleştirme:** API 4xx/5xx → tool `{ isError: true, content: [{ type: 'text', text: 'API error <status>: <body>' }] }` döner. Throw yerine.

6. **Path param şablonu:** `{platform}/{externalId}` interpolasyonu client'a çıkarılır:
   ```ts
   function buildPath(tpl: string, params: Record<string, string>): string
   ```

## Faz 0 — Server Tarafı Açık Sorular

Kod yazmadan önce cevaplanmalı (`server/app/Http/Controllers/Api/` incelenerek):

1. **`compare_app_keywords.app_ids`** — Swagger `items=integer`, açıklama "App IDs to compare (max 5)". → **Büyük ihtimalle internal id** (string olsaydı `external_id` olurdu). Faz 1 öncesi controller/request sınıfı okunarak doğrulanacak.
2. **`compare_app_keywords.version_ids`** — Swagger `type=object`, "Version ID per app (keyed by app ID)". Map formatı `{[app_id]: version_id}`. Doğrulama: integration test.
3. **App versiyonlarını listeleyen endpoint** — Swagger'da yok. `get_app` response'unun (`AppDetailResource`) versiyon listesini dönüp dönmediği kontrol edilecek. Dönmüyorsa iki seçenek:
   - A) Server'a `GET /apps/{platform}/{externalId}/versions` endpoint'i eklenmesi ayrı bir plana alınır.
   - B) `get_app_keywords.version_id` ve `compare_app_keywords.version_ids` parametreleri tool'a eklenir ama "son versiyon için boş bırak, spesifik versiyon için `list_app_changes` veya `get_app_listing` response'undaki `version_id`'yi kullan" diye açıklanır.
4. **`list_app_changes.app_id`** = internal id mi? Swagger `integer` diyor, `list_tracked_apps` `id` de integer — uyumlu görünüyor. Controller doğrulaması gerek.

## Faz 0 — Ortak Zod Yardımcıları (`src/tools/_schemas.ts`)

Tekrarı önlemek için:

```ts
export const Platform = z.enum(['ios', 'android']);
export const ExternalId = z.string().min(1);
export const CountryCode = z.string().length(2); // ISO-2, Swagger sadece string diyor ama semantik
export const Locale = z.string().min(2);
export const DateStr = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);
export const Ngram = z.union([z.literal(1), z.literal(2), z.literal(3)]);
export const Page = z.number().int().min(1);
```

`CountryCode.length(2)` Swagger'da yok; sadece `type=string`. **Bu kuralı gevşetiyoruz → `z.string()` bırakıyoruz** (Swagger-strict). Üst örnekteki `.length(2)` yerine `z.string()`.

## Tool Envanteri (Swagger-Strict Parametre Eşlemeleri)

Her tool için: **Endpoint** + **Zod şeması (Swagger'dan birebir)** + **Chain ipuçları**.

### Reference — `src/tools/reference.ts`

#### `list_categories` → `GET /store-categories`
```ts
input: {
  platform: Platform.optional(),          // Swagger: string, enum, opt
  type: z.enum(['app','game','magazine']).optional(),
}
```
**Chain:** returned `id` → `get_charts.category_id`, `browse_screenshots.category_id`, `browse_icons.category_id`.

#### `list_countries` → `GET /countries`
```ts
input: {}                                  // no parameters
```
**Chain:** returned `code` → any `country_code` field.

### Apps — `src/tools/apps.ts`

#### `list_tracked_apps` → `GET /apps`
```ts
input: {
  platform: Platform.optional(),
  search: z.string().optional(),
}
```
**Chain:** item `{id, platform, external_id}` — `id` → `list_app_changes.app_id`, `{platform, external_id}` → all app tools.

#### `search_store_apps` → `GET /apps/search`
```ts
input: {
  term: z.string().min(1),                // Swagger REQ
  platform: Platform,                     // Swagger REQ
  country_code: z.string().optional(),    // Swagger default=us; undefined → server uygular
  exclude_external_ids: z.array(z.string()).optional(),  // array<string>, client [] serializer'ı gerekli
}
```
**Client serializer:** `exclude_external_ids` → `exclude_external_ids[]=a&exclude_external_ids[]=b`.
**Chain:** each result `{platform, external_id, is_tracked}` → `get_app`.

#### `get_app` → `GET /apps/{platform}/{externalId}`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,                // path: {externalId} — tool-level snake_case, client path builder map'ler
}
```
**Chain:** `publisher.external_id` → `get_publisher`; `category.id` → chart/explorer.

#### `get_app_listing` → `GET /apps/{platform}/{externalId}/listing`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  country_code: z.string(),               // Swagger REQ
  locale: z.string().optional(),
}
```
**Chain:** `version_id` → `get_app_keywords.version_id`, `compare_app_keywords.version_ids`.

#### `get_app_sync_status` → `GET /apps/{platform}/{externalId}/sync-status`
```ts
input: { platform: Platform, external_id: ExternalId }
```
Terminal.

#### `get_app_rankings` → `GET /apps/{platform}/{externalId}/rankings`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  date: DateStr.optional(),               // format=date
  collection: z.enum(['top_free','top_paid','top_grossing','all']).optional(),
}
```
Not: Burada `'all'` enum değeri var; `/charts` endpoint'inde YOK (`top_free|top_paid|top_grossing` only). İki tool farklı enum kullanacak — dikkat.

### Competitors — `src/tools/competitors.ts`

#### `list_app_competitors` → `GET /apps/{platform}/{externalId}/competitors`
```ts
input: { platform: Platform, external_id: ExternalId }
```
**Chain:** each competitor `{platform, external_id}` → `get_app` (ikinci derece competitor araştırması).

#### `list_all_competitors` → `GET /competitors`
```ts
input: {
  platform: Platform.optional(),
  search: z.string().optional(),
}
```
**Chain:** groups `{parent, competitors[]}` — her ikisi de `{platform, external_id}`.

### Changes — `src/tools/changes.ts`

#### `list_app_changes` → `GET /changes/apps`
```ts
input: {
  per_page: z.number().int().optional(),          // Swagger int, default=50 (client boş → server uygular)
  page: z.number().int().optional(),              // default=1
  field: z.enum(['title','subtitle','description','whats_new','screenshots','locale_added','locale_removed']).optional(),
  platform: Platform.optional(),
  search: z.string().optional(),
  app_id: z.number().int().min(1).optional(),     // min=1 Swagger'dan
}
```
**Önemli:** `app_id` = internal id (from `list_tracked_apps`). Tool açıklamasında net.

#### `list_competitor_changes` → `GET /changes/competitors`
Aynı şema.

### Charts — `src/tools/charts.ts`

#### `get_charts` → `GET /charts`
```ts
input: {
  platform: Platform,                                                  // REQ
  collection: z.enum(['top_free','top_paid','top_grossing']),          // REQ — 'all' YOK
  country_code: z.string().optional(),                                 // default=us (server)
  category_id: z.number().int().optional(),
}
```
**Chain:** each entry `{platform, app_external_id, app_id}` → `get_app`; `category_id` ← `list_categories`; `country_code` ← `list_countries`.

### Keywords — `src/tools/keywords.ts`

#### `get_app_keywords` → `GET /apps/{platform}/{externalId}/keywords`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  locale: z.string().optional(),
  ngram: Ngram.optional(),                                             // enum [1,2,3]
  version_id: z.number().int().optional(),
  search: z.string().optional(),
  sort: z.enum(['keyword','count','density']).optional(),              // default=density
  order: z.enum(['asc','desc']).optional(),                            // default=desc
  per_page: z.number().int().min(1).max(500).optional(),               // default=100
  page: z.number().int().min(1).optional(),                            // default=1
}
```
**Chain:** `version_id` ← `get_app_listing.version_id` veya `list_app_changes[].version_id`.

#### `compare_app_keywords` → `GET /apps/{platform}/{externalId}/keywords/compare`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  app_ids: z.array(z.number().int()).min(1).max(5),                    // REQ, items=integer, "max 5"
  version_ids: z.record(z.string(), z.number().int()).optional(),      // object, map<app_id, version_id>
  locale: z.string().optional(),
  ngram: Ngram.optional(),
}
```
**Kritik:** `app_ids` = internal id (Faz 0'da doğrulanacak). `version_ids` key = stringified app id (JS object key), value = integer version_id.
**Client serializer:** object → `version_ids[1]=99&version_ids[2]=102`.

### Ratings — `src/tools/ratings.ts`

#### `get_rating_summary` → `GET /apps/{platform}/{externalId}/ratings/summary`
```ts
input: { platform: Platform, external_id: ExternalId }
```

#### `get_rating_history` → `GET /apps/{platform}/{externalId}/ratings/history`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  days: z.number().int().min(1).max(90).optional(),   // default=30 (server)
}
```

#### `get_rating_country_breakdown` → `GET /apps/{platform}/{externalId}/ratings/country-breakdown`
```ts
input: { platform: Platform, external_id: ExternalId }
```
iOS-only — tool description'da belirtilir.

### Publishers — `src/tools/publishers.ts`

#### `search_publishers` → `GET /publishers/search`
```ts
input: {
  term: z.string().min(1),                           // REQ
  platform: Platform,                                // REQ
  country_code: z.string().optional(),               // default=us
}
```

#### `list_user_publishers` → `GET /publishers`
```ts
input: {}                                            // NO PARAMS (Swagger)
```
Not: Önceki plan versiyonunda yanlışlıkla `name?` vardı — düzeltildi.

#### `get_publisher` → `GET /publishers/{platform}/{externalId}`
```ts
input: {
  platform: Platform,
  external_id: ExternalId,
  name: z.string().optional(),                       // Swagger'da var — atlanmayacak
}
```

#### `get_publisher_store_apps` → `GET /publishers/{platform}/{externalId}/store-apps`
```ts
input: { platform: Platform, external_id: ExternalId }
```

### Explorer — `src/tools/explorer.ts`

#### `browse_screenshots` → `GET /explorer/screenshots`
```ts
input: {
  platform: Platform.optional(),
  category_id: z.number().int().optional(),
  search: z.string().optional(),
  per_page: z.number().int().optional(),             // default=12 (server)
  page: z.number().int().optional(),                 // default=1
}
```

#### `browse_icons` → `GET /explorer/icons`
```ts
input: {
  platform: Platform.optional(),
  category_id: z.number().int().optional(),
  search: z.string().optional(),
  per_page: z.number().int().optional(),             // default=48 (server)
  page: z.number().int().optional(),                 // default=1
}
```

### Dashboard — `src/tools/dashboard.ts`

#### `get_dashboard` → `GET /dashboard`
```ts
input: {}                                            // NO PARAMS
```
**Chain:** `recent_changes[].app_id` → `list_app_changes.app_id`.

## Chain Haritası (Hızlı Referans)

```
list_categories ──► category.id ──► get_charts.category_id
                                ──► browse_screenshots.category_id
                                ──► browse_icons.category_id

list_countries  ──► code ─────► country_code (listing, search, charts, publisher search)

search_store_apps ──► {platform, external_id} ──► get_app ──► publisher.external_id ──► get_publisher
                                                          ──► get_publisher_store_apps

list_tracked_apps ──► id (internal)  ──► list_app_changes.app_id
                  ──► {platform, external_id} ──► (all app tools)

get_app_listing ──► version_id ──► get_app_keywords.version_id
                              ──► compare_app_keywords.version_ids

list_app_competitors ──► {platform, external_id} ──► (recurse)
list_all_competitors ──► parent & competitors ──► (recurse)

get_charts ──► {platform, app_external_id} ──► get_app

get_dashboard ──► recent_changes[].app_id ──► list_app_changes.app_id
```

## Faz 1 — Çekirdek Intelligence

Sıra:
1. `reference.ts` — `list_categories`, `list_countries`
2. `apps.ts` — `list_tracked_apps`, `search_store_apps`, `get_app`, `get_app_listing`, `get_app_sync_status`
3. `changes.ts` — `list_app_changes`, `list_competitor_changes`
4. `competitors.ts` — `list_app_competitors`, `list_all_competitors`
5. `register.ts` güncellenir, eski `meta.ts` silinir, `list_categories` oradan taşınır.

## Faz 2 — Analiz Derinliği

6. `charts.ts` — `get_charts`, `get_app_rankings`
7. `ratings.ts` — 3 tool
8. `keywords.ts` — `get_app_keywords`, `compare_app_keywords` (Faz 0 açık sorularından sonra)

## Faz 3 — Keşif

9. `publishers.ts` — 4 tool
10. `explorer.ts` — `browse_screenshots`, `browse_icons`
11. `dashboard.ts` — `get_dashboard`

## Test Planı

### Zod-Swagger Uyum Testleri (otomasyon fikri)

Opsiyonel ama değerli: TypeScript seviyesinde bir doğrulama script'i — `api-docs.json`'ı parse edip her tool'un zod şemasıyla Swagger parametrelerini karşılaştıran tek seferlik bir scripte. Faz 0'da yazılabilir; sonraki Swagger güncellemelerinde drift'i yakalar.

### Chain Testleri (zorunlu)

Her faz sonunda:
1. `list_categories` → id → `get_charts` → `app_external_id` → `get_app` → `get_rating_summary`
2. `list_tracked_apps` → `id` → `list_app_changes?app_id=...`
3. `search_store_apps("fitness","ios")` → sonuç → `get_app` → `publisher.external_id` → `get_publisher` → `apps[0]` → `get_app_listing(country_code="us")` → `version_id` → `get_app_keywords(version_id=...)`

### Type-Casting Smoke Testleri

- `get_app_rankings({ date: "2026-04-01" })` — date format string ile gider.
- `get_app_keywords({ ngram: 2, per_page: 50 })` — integer olarak query'ye yazılır, string olmamalı (Laravel validation zaten 422 verir).
- `search_store_apps({ exclude_external_ids: ["123","456"] })` → `exclude_external_ids[]=123&exclude_external_ids[]=456`.
- `compare_app_keywords({ app_ids: [1,2], version_ids: { "1": 10, "2": 11 } })` → `app_ids[]=1&app_ids[]=2&version_ids[1]=10&version_ids[2]=11`.

## Riskler

- **Swagger drift:** Swagger güncellenirse plan güncel kalmayabilir. Zod-Swagger uyum scripti CI'da çalıştırılabilir (out of scope bu plan için).
- **`version_ids` object serializer formatı:** Laravel `version_ids[1]=10` bracket formatını `array` olarak okur mu yoksa `Request->input('version_ids')` assoc array döner mi? Controller/FormRequest kontrolü Faz 0'da yapılmalı; gerekirse alternatif JSON-encoded string denenmeli.
- **Integer alanlarda MCP istemci farkı:** Bazı MCP istemcileri (Claude Desktop dahil) JSON Schema integer için tam integer gönderir; string değiller. Zod `z.number().int()` bunu garantiler.
- **Path param encoding:** `external_id` `?` veya `/` içerirse `encodeURIComponent` gerekli. Client path builder'ı bunu uygulayacak.
- **Response boyutu:** `list_app_changes` + `browse_*` büyük. Kullanıcıya `per_page` önerisi tool description'da.

## Versiyonlama

- Faz 1 biter bitmez `package.json` → `1.2.0` (minor bump).
- Faz 2 → `1.3.0`, Faz 3 → `1.4.0`. Ya da tek seferde bitirilirse `1.2.0`.

## Özet Checklist

- [ ] Faz 0: client array/object/hata normalleştirme + path builder
- [ ] Faz 0: server açık soruları (keyword app_ids tipi, version endpoint) yanıtla
- [ ] Faz 0: `_schemas.ts` ortak zod'lar
- [ ] Faz 1: reference + apps + changes + competitors (12 tool)
- [ ] Faz 2: charts + ratings + keywords (7 tool)
- [ ] Faz 3: publishers + explorer + dashboard (7 tool)
- [ ] Chain entegrasyon testleri
- [ ] `package.json` versiyon bump
- [ ] Zod-Swagger uyumunu manuel karşılaştır (Swagger-strict doğrulaması)
