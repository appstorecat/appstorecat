# Plan: Web manuel tip ve axios çağrılarının Orval'a geçişi

**Durum:** Backlog
**Kapsam:** `web/src/` — `api/` üretilmiş dizini hariç.
**İlgili pipeline:** `make swagger` + `make api-generate` (Orval v8.6.2).

## Bağlam

`server/` tarafındaki `Api/V1/*` kontrolcülerinin hepsi artık `#[OA\Schema]`
attribute'lu dedike `*Resource` / `*Request` sınıflarına sahip. Bu sayede
`make swagger` çıktısı kapsamlı, Orval de `web/src/api/endpoints/` ve
`web/src/api/models/` altında eksiksiz tipli fetcher + hook üretiyor
(`useShowApp`, `useListApps`, `useGetCharts`, `useListCompetitors`,
`useListAllCompetitors`, `useAppChanges`, `useListCountries`,
`useListStoreCategories`, `useSearchApps`, `useSearchPublishers`,
`useShowPublisher`, `usePublisherStoreApps`, `useListApiTokens`, ...).

Ama `web/src/pages` ve `web/src/components` altındaki birçok dosya hâlâ:

1. API response / request shape'ini temsil eden **manuel `interface` / `type`**
   deklare ediyor,
2. `axios.get/post/put/patch/delete('/...')` çağrısıyla endpoint'i kendi
   elleriyle kuruyor — Orval karşılığı hazır olduğu halde.

İki riski var:
- **Drift**: Backend resource bir alan ekler/çıkarırsa Orval tipi güncellenir
  ama manuel tip kalır. Çalışma anında hata, IDE sessiz.
- **Çoğaltma**: Aynı shape birden çok dosyada yeniden yazılmış (örn.
  `CompetitorApp`, `StoreCategory`, `SearchResult`), tutarsızlık kaçınılmaz.

## Hedef

`web/src/` içinde API ile konuşan tüm noktaları Orval üretilmiş hook/tiplerine
taşıyıp manuel `axios.*` + elle yazılmış response tiplerini silmek.

## Bulgular

### 1. Manuel tip + manuel `axios` — Orval karşılığı tam hazır

| Dosya:satır | Manuel tip(ler) | Orval modeli | Orval hook'u | Durum |
|-------------|-----------------|--------------|--------------|-------|
| `web/src/pages/discovery/Trending.tsx:30,45,57` | `ChartEntry`, `ChartResponse`, `StoreCategory` | `ChartEntryResource`, `GetCharts200`, `StoreCategoryResource` | `useGetCharts`, `useListStoreCategories` | Drift ufak: `ChartEntry.price` manuel `number \| null`, Orval `number` (zorunlu). Manuel `meta.message` Orval'da yok. |
| `web/src/pages/competitors/Index.tsx:10,26` | `CompetitorApp`, `CompetitorGroup` | `AppResource`, `CompetitorResource`, `CompetitorGroupResource` | `useListAllCompetitors` | Eşit — backend zaten `CompetitorGroupResource` döndürüyor. |
| `web/src/pages/apps/Index.tsx:52` | (response tipi yok, `Record<string, unknown>[]`) | `AppResource` | `useListApps` | Tip bile yok; hook'a geç + `AppResource[]` al. |
| `web/src/pages/apps/Show.tsx:110,118,129,131` | (inline anonim tipler) | `AppDetailResource`, `CompetitorResource` | `useShowApp`, `useListCompetitors`, `useTrackApp`, `useUntrackApp` | `app?.is_tracked`, `app.listings`, `app.versions` kullanımları `AppDetailResource` ile eşleşiyor. Geçiş düz. |
| `web/src/pages/discovery/Apps.tsx:13,39,47,49` | `SearchApp` | `AppSearchResultResource` (sadece `external_id`, `name`, `publisher_name`, `icon_url`) — bkz. §Schema eksikleri | `useSearchApps`, `useTrackApp`, `useUntrackApp` | **Drift/eksik**: manuel `SearchApp`'te `rating`, `rating_count`, `version`, `is_available`, `is_tracked`, `platform`, `id`, `category`, `publisher` var; Orval `AppSearchResultResource`'ta yok. Backend resource'un zenginleştirilmesi gerek. |
| `web/src/pages/discovery/Publishers.tsx:11,29` | `PublisherResult` | `PublisherSearchResultResource` | `useSearchPublishers` | Eşit. |
| `web/src/pages/publishers/Index.tsx:12,21,41,47` | `PublisherData`, `SearchResult` | `PublisherResource` (liste), `PublisherSearchResultResource` (arama) | `useListPublishers`, `useSearchPublishers` | Drift: manuel `PublisherData`'da `id`, `external_id`, `platform`, `url`, `apps_count` — Orval `PublisherResource` `Publisher` extends, çoğu alan optional. Kontrol edilip eşleştirilebilir. |
| `web/src/pages/publishers/Show.tsx:11,38,40,47,64,66,87` | `StoreApp` + `any` | `PublisherDetailResource`, `StoreAppResource`, `PublisherStoreApps200` | `useShowPublisher`, `usePublisherStoreApps`, `useTrackApp`, `useUntrackApp` | Drift: manuel `StoreApp.platform` yok Orval'da, `category` manuel `string \| null` Orval'la eşleşiyor. `PublisherStoreApps200 = {apps?: StoreAppResource[]}` zarfını unutma. |
| `web/src/pages/changes/AppChanges.tsx:5,26` | `Change` (inline `app` tipi dahil) | `ChangeResource` (+ `ChangeResourceApp`) | `useAppChanges` | **Drift riski yüksek**: manuel `Change.language` var, Orval `ChangeResource` / `StoreListingChange`'de alan adı `locale`. (`ChangeCard` zaten `change.locale` okuyor — manuel tip zaten yanlış.) |
| `web/src/pages/changes/CompetitorChanges.tsx:5,26` | `Change` | `ChangeResource` | `useCompetitorChanges` | Aynı `language` vs `locale` driftı. |
| `web/src/pages/explorer/Icons.tsx:17,27,34,72,84` | `AppIcon`, `StoreCategory`, `ApiResponse` | `ExplorerIconResource`, `StoreCategoryResource`, `ExploreIcons200` | `useExploreIcons`, `useListStoreCategories` | Eşit. `useInfiniteQuery` + Orval'ın `queryFn`'i için ek glue gerekir. |
| `web/src/pages/explorer/Screenshots.tsx:17,23,34,41,79,91` | `Screenshot`, `AppScreenshots`, `StoreCategory`, `ApiResponse` | `ExplorerScreenshotResource`, `StoreListingScreenshotsItem` (shape olarak), `ExploreScreenshots200` | `useExploreScreenshots`, `useListStoreCategories` | **Schema driftı**: `ExplorerScreenshotResource.screenshots: string[]` — manuel tip `{url, device_type, order}[]` bekliyor, controller gerçekten öyle döndürüyor. Backend resource'unda `screenshots` tipi yanlış; §Schema eksikleri. |
| `web/src/pages/settings/ApiTokens.tsx:11,43,59,75` | `ApiToken` | `ApiTokenResource`, `ApiTokenCreatedResource` | `useListApiTokens`, `useCreateApiToken`, `useRevokeApiToken` | Eşit. Bonus: komponent `useEffect + useState` ile fetch ediyor — TanStack Query'ye taşırken hook karşılığı zaten var. |
| `web/src/pages/Settings.tsx:26,78,139` | (yok) | `ProfileUpdateRequest`, `PasswordUpdateRequest`, `ProfileDeleteRequest` | `useUpdateProfile`, `useUpdatePassword`, `useDeleteProfile` | Sadece `axios.*` kullanıyor; Orval mutation'larına düz geçiş. |
| `web/src/stores/auth.ts:4,29,36,49,64` | `User`, `AuthState` | `User`, `LoginResource` (`{user, token}`), `LoginRequest`, `RegisterRequest` | `useLogin`, `useRegister`, `useLogout`, `useMe` | Zustand store'u fetcher'ları Orval mutation'larına çağırabilir; hook kullanma zorunluluğu yok, sadece `login`/`register`/`me` fonksiyonları. Manuel `User` yerine `@/api/models/user`. |
| `web/src/hooks/useSyncStatus.ts:5,16,44,64,82` | `FailedItem`, `SyncStatus` | `SyncStatusResource`, `SyncStatusResourceFailedItemsItem` | `useAppSyncStatus`, `useSyncApp` | Drift: manuel `SyncStatus.current_step` union `'identity' \| 'listings' \| ...`, Orval `string \| null`. Backend tarafında enum'u `#[OA\Schema]` ile daraltmak iyileştirir. |
| `web/src/components/AppCard.tsx:10,50,52` | inline `AppCardProps.app` | `AppResource` | `useTrackApp`, `useUntrackApp` (track toggle için) | Prop tipi doğrudan `AppResource` olabilir. Manuel inline shape silinir. |
| `web/src/components/CountrySelect.tsx:9,20` | `Country` (export) | `CountryResource` | `useListCountries` | Eşit. `useCountries` custom hook'u `useListCountries`'i sarmalayabilir veya kalabilir. |
| `web/src/components/tabs/CompetitorsTab.tsx:27,42,58,90,110,129` | `CompetitorApp`, `Competitor`, `SearchResult` | `AppResource`, `CompetitorResource`, `AppSearchResultResource` | `useSearchApps`, `useStoreCompetitor`, `useDeleteCompetitor` | `Competitor` → `CompetitorResource` tam oturur. `SearchResult` → `AppSearchResultResource` (yine §Schema eksikleri: manuel `id` var Orval'da yok). |
| `web/src/components/tabs/StoreListingTab.tsx:5,11,25` | `Screenshot`, `StoreListingData`, `AppVersionData` | `StoreListingScreenshotsItem`, `StoreListing`/`ListingResource`, `AppVersion`/`VersionResource` | (prop alıyor, direkt çağrı yok) | Eşit; prop tiplerini Orval modelleriyle değiştir. |
| `web/src/components/tabs/VersionsTab.tsx:5` | `AppVersionData` | `AppVersion` / `VersionResource` | — | Eşit. |
| `web/src/components/tabs/ChangesTab.tsx:30,42` | `ListingData`, `AppVersionData` | `StoreListing`, `AppVersion` | — | Eşit. |
| `web/src/components/tabs/KeywordsTab.tsx:32,37,44,51` | `AppVersionData`, `KeywordData`, `CompareApp`, `CompareResponse` | `AppVersion`, `KeywordDensityResource`, `KeywordCompareResourceAppsItem`, `KeywordCompareResource` | `useAppKeywords`, `useCompareKeywords` | Eşit. |
| `web/src/components/tabs/RankingsTab.tsx:10-19,51,54` | `CurrentRanking` (extend), `RankStatus`, `Collection` | `AppRankingResource`, `AppRankingResourceStatus` | `useListAppRankings` | Zaten kısmen Orval tipi kullanıyor; `axios.get('/apps/.../rankings')` → `useListAppRankings`. `CurrentRanking` zaten `AppRankingResource & {...}`; bu `& { ... required }` kısmı driftten çok narrowing — kalabilir. |
| `web/src/layouts/AppLayout.tsx:49,51` | `any` | `AppDetailResource` | `useShowApp` | Eşit. |

### 2. Manuel `axios` çağrısı — Orval hook'una düz geçiş (tip farkı yok)

Yukarıdaki tabloda zaten listeli. Özet: `/apps`, `/apps/search`, `/apps/{platform}/{externalId}`, `/apps/{...}/competitors`, `/apps/{...}/competitors/{id}`, `/apps/{...}/track`, `/apps/{...}/sync`, `/apps/{...}/sync-status`, `/apps/{...}/rankings`, `/apps/{...}/keywords`, `/apps/{...}/keywords/compare`, `/charts`, `/store-categories`, `/competitors`, `/countries`, `/publishers`, `/publishers/search`, `/publishers/{platform}/{externalId}`, `/publishers/{...}/store-apps`, `/changes/apps`, `/changes/competitors`, `/explorer/icons`, `/explorer/screenshots`, `/account/api-tokens`, `/account/api-tokens/{id}`, `/account/profile`, `/account/password`, `/auth/login`, `/auth/register`, `/auth/logout`, `/auth/me` — hepsinin Orval karşılığı var.

### 3. Orval karşılığı olmayan / yarım olan çağrılar

Yok — taramada `axios.*` çağrısı yaptığı hâlde Orval'da karşılığı bulunmayan endpoint kalmadı. Not: bu planın **frontend tarafı için** bir blocker üretmiyor; ama schema'lar eksik alanlar içeriyor (aşağıda).

## Schema eksikleri (ayrı iş parçası)

Backend resource / `#[OA\Schema]` tanımındaki kayıp alanlar. Bu plana dahil **değil** — ikinci bir iş parçasıdır; `server/` tarafında `Api/Resources/*Resource.php` güncellemesi ve `make swagger` yeniden çalıştırılması gerekir.

1. **`AppSearchResultResource`** (`/apps/search`): frontend `SearchApp` ve `CompetitorsTab.SearchResult` `id`, `platform`, `rating`, `rating_count`, `version`, `is_available`, `is_tracked`, `publisher`, `category` bekliyor; schema sadece `external_id`, `name`, `publisher_name`, `icon_url` tanımlıyor. Hem `pages/discovery/Apps.tsx` (track toggle için `is_tracked` lazım) hem `CompetitorsTab` (competitor eklerken `id` lazım) gerçekte bunları alıyor — schema güncellenmeli.
2. **`ExplorerScreenshotResource.screenshots`**: Orval tipi `string[]`, gerçek payload `{url, device_type, order}[]`. Backend resource'unda `OA\Property` type'ı yanlış; `StoreListingScreenshotsItem` gibi bir `items` şeması kullanmalı.
3. **`ChangeResource` (`StoreListingChange`) alan adı**: frontend `change.locale` kullanıyor, manuel tip `language` yazmış — tutarsız. Orval `StoreListingChange`'i incelenip alan adının `locale` olduğu doğrulanmalı; manuel tipin yanlış olduğu kesin.
4. **`GetCharts200Meta.message`**: frontend `chart?.meta?.message` okuyor (nadiren; boş veri senaryosu). Orval `GetCharts200Meta`'ya opsiyonel `message?: string` eklenmeli veya frontend'den düşürülmeli.
5. **`SyncStatusResource.current_step`**: schema `string | null`; gerçek değerler `'identity' | 'listings' | 'metrics' | 'finalize' | 'reconciling'`. Enum olarak `#[OA\Schema]` ile daraltmak UI narrowing'i bedava yapar. `FailedItem.type` için de aynısı (`'listing' | 'metric'` + yeni `'metric_breakdown'` — bkz. `metric-breakdown-retry.md`).
6. **`StoreCategoryResource.type`**: schema `string | null`, frontend `'app'` gibi sabit değerlere bel bağlıyor. `ListStoreCategoriesType` enum'u var (`'app' | 'game'`) ama resource'a bağlı değil.

## Yaklaşım

Pattern (Trending.tsx örneği üzerinden):

**Before:**
```tsx
interface ChartEntry { rank: number; /* ... */ }
interface ChartResponse { data: ChartEntry[]; meta: {...} }

const { data: chart } = useQuery<ChartResponse>({
  queryKey: ['charts', platform, collection, countryCode, categoryId],
  queryFn: () =>
    axios.get('/charts', { params: { platform, collection, country_code: countryCode, ... } })
         .then((r) => r.data),
})
```

**After:**
```tsx
import { useGetCharts } from '@/api/endpoints/charts/charts'
import type { GetChartsPlatform, GetChartsCollection } from '@/api/models'

const { data: chart } = useGetCharts({
  platform: platform as GetChartsPlatform,
  collection: collection as GetChartsCollection,
  country_code: countryCode,
  ...(categoryId ? { category_id: Number(categoryId) } : {}),
})
// chart is `GetCharts200` now
```

Dikkat edilecekler:
- Orval fetcher'ları `fetch` tabanlı; `web/src/lib/axios.ts` `Authorization` header'ını axios interceptor'ı üzerinden ekliyor. Orval'a geçerken ya:
  (a) Orval config'i custom `mutator` ile mevcut `axios` instance'ına yönlendirilmeli, **veya**
  (b) `fetch` base URL + auth header (Bearer token `localStorage.getItem('token')`) bir global interceptor / mutator'da set edilmeli.
  Bu **blocker** — bu plan başlamadan netleşmeli (aşağıda "Açık kararlar").
- TanStack Query key'leri değişir (Orval kendi key'ini üretir). Invalidation çağrıları güncellenmeli: `queryClient.invalidateQueries({ queryKey: ['apps'] })` → `queryClient.invalidateQueries({ queryKey: getListAppsQueryKey() })`.
- `useInfiniteQuery` kullanımı (`Icons.tsx`, `Screenshots.tsx`): Orval `useInfiniteX` üretmiyor; manuel `useInfiniteQuery` kalır ama `queryFn: () => exploreIcons({...})` ve `TData = ExploreIcons200` Orval'dan gelir.

## Fazlama

Üç opsiyon:

1. **Tek PR, tüm dosyalar.** 20+ dosya. Risk: review yükü büyük; axios → fetch geçişi yan etkisi tüm app'te bir anda sınanır. Avantaj: `@/lib/axios` + manuel tipler tek ayıklamayla silinir.
2. **Domain-by-domain PR.** Sıra önerisi (basitten karmaşığa): (1) `account` + `auth` (settings + stores/auth), (2) `countries` + `store-categories` (tek çağrı), (3) `change-monitor` (2 sayfa), (4) `charts` + `explorer` (infinite query dikkat), (5) `publishers` (3 dosya), (6) `apps` + `competitors` + tabs (en büyük). Her adım bağımsız shippable.
3. **Incremental, manuel tipler önce.** İlk PR sadece `interface X { ... }` → `import type { XResource } from '@/api/models'`. `axios.*` çağrıları kalır. İkinci PR axios → Orval hook. Avantaj: tip drifti hemen kapatılır, hook geçişi ayrı test edilir. Dezavantaj: ara dönemde `AxiosResponse<AppResource>` gibi karışımlar.

Önerilen: **2 (domain-by-domain)**, çünkü auth/axios interceptor soru işaretini erken sahaya indirir. Küçük domain (`countries`) ilk gitsin, pattern oturduktan sonra büyükler.

## Kapsam dışı

- Orval config değişikliği (mutator, base URL, auth header). Bu plan başlamadan ayrı bir karar/PR gerektirir; "Açık kararlar"da.
- Yeni endpoint eklemek. Schema eksikleri (`AppSearchResultResource` genişletme, `ExplorerScreenshotResource` düzeltme, `SyncStatus` enum'ları) ayrı backend işidir.
- Response shape refactor (örn. `/publishers/.../store-apps`'i `{apps: [...]}` yerine düz array döndürmek).
- `web/src/api/` içine dokunma (üretilmiş, manuel editlenmez).
- TanStack Query key invalidation stratejisinin tümden elden geçmesi — geçişte minimal tutulacak.

## Açık kararlar

1. **Axios → Orval geçişinde auth/HTTP katmanı**: Orval fetcher'ları özel axios instance'ına bir mutator ile yönlendirilsin mi, yoksa fetch tabanlı yeni bir `apiClient` (Bearer + interceptor + 401 redirect) mi yazılsın? Önerilen: Orval mutator + mevcut `@/lib/axios`. Bu kararlaşmadan geçişin hiçbir parçası shippable değil.
2. **Manuel tipler tamamen silinsin mi, `satisfies`/`extends` ile narrowing korunsun mu?** Örn. `RankingsTab.CurrentRanking = AppRankingResource & { country_code: string; rank: number; ... }` — Orval'da çoğu alan `optional`; ama controller runtime'da hepsini dolduruyor. Önerilen: component içinde `type X = Required<Pick<AppRankingResource, '...'>> & AppRankingResource` ya da backend resource'unda `required` tanımlamak. İlki hızlı, ikincisi doğru.
3. **Sadece manuel tipleri silmeli, `axios.*` çağrılarına dokunmamalı mıyız?** (Opsiyon 3 yukarıda.) Küçük sprintte drift'in çoğunu kapatıp, hook geçişini ayrı sprinte atmak mümkün. Tercih ekibe bırakıldı.
4. **`useSyncStatus` ve `stores/auth` gibi custom hook/store'lar**: Orval hook'unu sarmalasın mı, yoksa çağıran yer doğrudan Orval hook'una mı geçsin? `useSyncStatus`'un `refetchInterval` ve auto-trigger mantığı var — sarmalayıcı kalsın, içten `useAppSyncStatus` + `useSyncApp` kullansın mantıklı.
5. **ChangeCard'taki `change.locale` vs `Change.language` tutarsızlığı** — bu zaten `pages/changes/*` seviyesinde bug. Plana girmeden önce bir smoke test ile `/changes/apps` response'unun alan adı doğrulanmalı; uygun olanla manuel tip silinir ya da backend alias eklenir.
