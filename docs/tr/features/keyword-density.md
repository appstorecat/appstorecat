# Anahtar Kelime Yogunlugu

Uygulama magaza listelerindeki anahtar kelime kullanimini n-gram cikarma ve uygulamalar arasi karsilastirma ile analiz edin.

![Anahtar Kelime Yogunlugu](../../screenshots/keyword-density.jpeg)

## Genel Bakis

AppStoreCat, magaza listelerinden anahtar kelimeleri cikarir ve siklik ile yogunluklarini hesaplar. Bu, App Store Optimizasyonu (ASO) icin bir uygulamanin hangi anahtar kelimeleri hedefledigi ve bunlarin rakipler arasinda nasil karsilastirildigi gostererek yardimci olur.

## Nasil Calisir

Anahtar kelime yogunlugu her istek sirasinda mevcut `StoreListing` uzerinden aninda hesaplanir — istekler arasinda hicbir sey kaydedilmez. Bu sayede analiz her zaman en guncel liste metnini yansitir; ayri bir yeniden indeksleme adimina gerek yoktur.

1. Anahtar kelime ucu, istenen platform + external ID + dil icin eslesen `StoreListing` kaydini yukler
2. Baslik + alt baslik + aciklama + yenilikler metinleri birlestirilir
3. Icerik, dil duyarli dur-kelimesi filtrelemesiyle tokenize edilir
4. N-gramlar (1 kelimelik, 2 kelimelik, 3 kelimelik kombinasyonlar) cikarilir
5. Siklik ve yogunluk yuzdeleri hesaplanir ve yanitta dondurulur

## Dur-Kelimesi Filtreleme

Analizci, **50 dil** icin dur-kelimesi sozlukleri icerir. Dur-kelimeleri ("the", "and", "is" gibi yaygin kelimeler) anlamli anahtar kelimeleri ortaya cikarmak icin filtrelenir.

Dur-kelimesi dosyalari `server/resources/data/stopwords/{lang}.json` konumunda saklanir.

## N-gram Destegi

| N-gram Boyutu | Ornek |
|---------------|-------|
| 1 (unigram) | `photo`, `editor`, `filter` |
| 2 (bigram) | `photo editor`, `social media` |
| 3 (trigram) | `photo editing app`, `free music player` |

## API

### Anahtar Kelime Yogunlugu

```
GET /api/v1/apps/{platform}/{externalId}/keywords?language=en-US&ngram=2
```

Belirtilen liste icin anahtar kelimeleri sayi ve yogunluk yuzdeleriyle birlikte dondurur. Analizci, her istekte mevcut saklanan liste uzerinde yeniden calisir; sonuclar en son senkronizasyon verilerini otomatik yansitir.

### Anahtar Kelime Karsilastirmasi

```
GET /api/v1/apps/{platform}/{externalId}/keywords/compare?app_ids=1,2,3&language=en
```

Birden fazla uygulama arasinda anahtar kelime kullanimini karsilastirir -- rekabetci anahtar kelime analizi icin kullanislidir.

## Arayuz

Uygulama detay sayfasindaki **Keywords** sekmesi sunlari gosterir:
- Yogunluga gore siralanmis anahtar kelime listesi
- N-gram boyutu filtresi (1, 2, 3)
- Dil secici
- Rakip uygulamalarla karsilastirma gorunumu

## Teknik Detaylar

- **Servis:** `KeywordAnalyzer` (`analyzeListing()` / `analyzeText()` dizi dondurur — DB yazmaz)
- **Kaynak:** Istenen `(app_id, language)` icin mevcut `StoreListing` kaydi
- **Controller'lar:** `KeywordController@index` ve `KeywordController@compare` her istekte yeniden hesaplar
- **Minimum kelime uzunlugu:** 2 karakter
