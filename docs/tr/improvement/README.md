# İyileştirme Notları

Bu klasör, tek bir hata düzeltmesinden daha büyük ama bir yol haritası
maddesinden daha küçük olan tasarım düzeyindeki önerileri ve teknik borç
notlarını bir araya getirir. Buradaki hiçbir şey henüz kod tabanına
uygulanmamıştır — her belge, gözden geçirilmesi, önceliklendirilmesi ve
planlanması gereken fikirlerin bir listesidir.

## Kapsam

İyileştirme notları şunlara odaklanır:

- **Şema değişiklikleri** — index'ler, foreign key'ler, kolon tipi
  sıkılaştırmaları, partitioning, veri saklama (retention).
- **Çapraz kesen konular** — adlandırma kuralları, enum'lar, soft delete,
  locale yönetimi.
- **Gözlemlenebilirlik** — hataları teşhis etmeye yardımcı ek kolonlar veya
  tablolar (ör. faz başına sync ilerlemesi).

Uygulama düzeyinde refactor'lar, API değişiklikleri veya UX çalışması
burada yer almaz — onlar `architecture/` veya `features/` altına aittir.

## Dizin

| Belge | Konu |
|-------|------|
| [database-tables.md](./database-tables.md) | Tablo bazında şema incelemesi, kritik DB kaynaklı bug'lar, öncelik matrisi |

## Diğer belgelerle ilişki

- **`architecture/data-model.md`** — şemayı bugünkü haliyle anlatır.
- **`bugs/report_20apr.md`** — çok ülkeli sync yeniden yazımı sırasında
  ortaya çıkan canlı hataları kayıt altına alır; DB ile ilgili alt küme,
  burada şema önerileri olarak yeniden çerçevelenmiştir.
- **`improvement/`** (bu klasör) — şemayı olmasını istediğimiz haliyle,
  migration parçacıkları ve etki notlarıyla anlatır.

Bir iyileştirme notu uygulandığında ilgili bölüm
`architecture/data-model.md` içine taşınmalı ve buradan kaldırılmalıdır;
bu klasörün yalnızca açık önerilere odaklı kalması hedeflenir.
