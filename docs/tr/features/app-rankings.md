# Uygulama Siralamalari

Takip edilen bir uygulamanin secilen bir gunde, tum ulke, koleksiyon ve kategori listelerinde nerede yer aldigini gorun.

## Genel Bakis

[Trend Listeler](trending-charts.md) tek bir listeye goz atmanizi saglarken (ornegin ABD iOS En Cok Indirilen Ucretsiz), Siralamalar gorunumu ekseni tersine cevirir: bir uygulama ve tarih secin, o gun dunya capinda tuttugu tum liste pozisyonlarini gorun.

Pozisyonlar, gunluk liste senkronizasyonunun topladigi ayni `ChartSnapshot` + `ChartEntry` verisinden gelir; senkronizasyon icin aktif edilen her ulke/koleksiyon/kategori burada otomatik olarak gorunur.

## Nasil Calisir

1. Uygulama detay sayfasinda **Rankings** sekmesini acin
2. Bir **siralama turu** secin (Any / Top Free / Top Paid / Top Grossing)
3. Gunler arasinda gecmek icin ileri/geri oklariyla birlikte **tarih seciciyi** kullanin
4. Pivot tabloda satirlar ulkeleri, sutunlar `(koleksiyon, kategori)` ciftlerini gosterir
5. Her hucre, uygulamanin sirasini en yakin onceki anlik goruntuye gore degisim rozetiyle birlikte gosterir

## Degisim Gostergeleri

| Durum | Anlami |
|-------|--------|
| `new` | Uygulama bu listede ilk kez goruluyor |
| `up` | Siralama onceki anlik goruntuye gore yukseldi |
| `down` | Siralama onceki anlik goruntuye gore dustu |
| `same` | Siralama degismedi |

O gun icin giris bulunmayan hucreler `N/A` gosterir.

## API

```
GET /api/v1/apps/{platform}/{externalId}/rankings?date=2026-04-17
```

`date` query parametresi opsiyoneldir (varsayilan bugun) ve `Y-m-d` formatinda olmalidir. Yanit ogeleri sunlari icerir:

```
country_code, collection, category, rank, previous_rank, rank_change, status, snapshot_date
```

`previous_rank`, ayni `(platform, collection, country_code, category_id)` cifti icin en yakin onceki anlik goruntuden alinir.

## Arayuz

Uygulama detay sayfasinin icinde bir sekme olarak uygulanmistir (`web/src/pages/apps/Show.tsx`), bilesen `web/src/components/tabs/RankingsTab.tsx` dosyasindadir. Satirlar, uygulamanin her ulkedeki en iyi siralamasina gore siralanir.

## Teknik Detaylar

- **Kaynak veri:** `trending_charts`, `trending_chart_entries`
- **Controller:** `AppRankingController@index`
- **Resource:** `AppRankingResource`
- **Bagimlilik:** Ilgili ulkeler icin gunluk liste senkronizasyonunun aktif olmasi
