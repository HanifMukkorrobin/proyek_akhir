# Alur Geocoding Service

## Tujuan
Dokumen ini menjelaskan alur geocoding yang sudah diimplementasikan pada engine klasifikasi alamat saat ini, mulai dari preprocessing, pemetaan wilayah internal, sampai fallback external geocoding (Nominatim).

## Entry Point API
- Endpoint: `POST /public/test-klasifikasi-alamat`
- Controller menerima:
  - `alamat` (array string, opsional)
  - `use_external_geocoding` (boolean, opsional)
- Jika `alamat` kosong, sistem memakai dataset default untuk pengujian.

## Komponen Utama
- `PublicAddressClassifierController`
- `AddressWilayahClassifierRepository`
- `WilayahDictionaryRepository`
- `NominatimGeocodingRepository`

## Ringkasan Alur
1. API menerima daftar alamat.
2. Tiap alamat diproses internal pipeline untuk menghasilkan:
   - normalisasi
   - mapping wilayah
   - koordinat internal
   - confidence
   - flag `needs_confirmation`
3. Jika external geocoding aktif dan hasil internal perlu bantuan, sistem memanggil Nominatim dengan query yang dibangun dari hasil mapping internal (bukan dari raw address).
4. Hasil external dipakai untuk:
   - enrichment metadata external
   - optional remapping dengan hint address
   - fallback koordinat jika internal tidak punya lat/lon

## Detail Internal Pipeline

### 1) Normalisasi Teks
Urutan normalisasi:
1. Ubah alamat ke uppercase.
2. Bersihkan karakter spesial dan rapikan delimiter.
3. Ekstrak hint administratif:
   - provinsi
   - kabupaten_kota
   - kecamatan
   - desa
4. Hapus stop words administratif umum (JL, RT, RW, KEC, KAB, dll).
5. Tokenisasi per chunk (berdasarkan koma/titik), termasuk unigram, bigram, trigram.
6. Buang token yang mengandung angka.
7. Deduplikasi token.

### 2) Pencarian Kandidat Wilayah
Untuk setiap token:
1. Exact match terhadap dictionary key.
2. Contains match terhadap key dengan prefix sama.
3. Fuzzy match jika exact/contains tidak menghasilkan kandidat yang cukup.

Catatan fuzzy:
- Threshold fuzzy adaptif berdasarkan panjang token.
- Token pendek dibuat lebih ketat untuk menekan false positive.

### 3) Scoring dan Ranking Kandidat
Setiap kandidat diberi skor gabungan:
- skor dasar match (exact/contains/fuzzy)
- hierarchy context score (dukungan node parent yang juga terdeteksi)
- bonus hint administratif (exact/partial/fuzzy)
- bobot hierarchy completeness

Candidate lalu diurutkan berdasarkan `rank_score`.

### 4) Pemilihan Anchor dan Mapping Hierarki
1. Pilih anchor terbaik dari ranking kandidat.
2. Jika kandidat level desa kuat, sistem memprioritaskan anchor level desa.
3. Bangun chain hierarki dari anchor:
   - provinsi
   - kabupaten_kota
   - kecamatan
   - desa
4. Simpan related regions per level (top kandidat untuk diagnosis).

### 5) Validasi Konsistensi Hierarki
Sistem melakukan parent-child consistency check:
- kabupaten_kota -> provinsi
- kecamatan -> kabupaten_kota
- desa -> kecamatan

Output validasi:
- `status`: `consistent`, `inconsistent`, atau `no_data_to_validate`
- daftar issue jika ada mismatch
- alignment report per level

### 6) Confidence Scoring
Confidence dihitung dari 4 komponen:
- `anchor_score` (45%)
- `gap_score` top-1 vs top-2 candidate (25%)
- `hierarchy_score` dari hasil validasi (20%)
- `hint_coverage_score` dari coverage hint administratif (10%)

Output confidence ada di:
- `mapping.confidence.score`
- `mapping.confidence.components`

### 7) Keputusan Needs Confirmation
`needs_confirmation = true` jika salah satu kondisi berikut terjadi:
- anchor tidak ditemukan
- validasi hierarki `inconsistent`
- confidence di bawah threshold review

Jika `needs_confirmation = true`, API mengembalikan `top_candidates_for_review` (top 3) agar mudah diverifikasi manual.

## Internal Geocoding
Koordinat internal diambil dari chain wilayah hasil mapping, dengan urutan prioritas:
1. desa
2. kecamatan
3. kabupaten_kota
4. provinsi

Koordinat pertama yang valid (lat dan lon tersedia) akan dipakai sebagai hasil internal.

## External Geocoding Fallback (Nominatim)

### Kapan dipanggil
External dipanggil jika:
- external geocoding diaktifkan, dan
- hasil internal masih butuh konfirmasi, atau
- hasil internal belum punya koordinat.

### Sumber query external
Query Nominatim dibangun dari hasil mapping internal:
- desa, kecamatan, kabupaten_kota, provinsi
- ditambah suffix `INDONESIA`

Prinsip penting:
- external tidak memakai raw address sebagai query utama
- external dipakai sebagai penguat/enrichment dari hasil internal

### Proses external
1. Panggil endpoint Nominatim `/search` (jsonv2, limit 1, addressdetails=1).
2. Simpan metadata external:
   - provider
   - display_name
   - lat/lon
   - importance
   - hint_address
   - query_source
   - query_address
3. Jika Nominatim mengembalikan `addressdetails`, sistem membangun `hint_address` lalu menjalankan internal pipeline sekali lagi untuk mengecek apakah mapping lebih baik.
4. Jika koordinat internal kosong dan external punya koordinat, sistem mengisi koordinat dari external dan menandai source `external_nominatim`.

## Struktur Output Terkait Geocoding

### Bagian utama
- `mapping`
  - `status`
  - `anchor`
  - `wilayah` (provinsi/kabupaten_kota/kecamatan/desa)
  - `confidence`
  - `hierarchy_validation`
  - `related_regions_array`
  - `top_candidates_for_review`
- `geocoding`
  - `latitude`
  - `longitude`
  - `source_wilayah_id`
  - `source_level`
  - `source` (`internal_wilayah` atau `external_nominatim`)
  - `external_geocoding_used`
- `external_geocoding`
  - `used`
  - `provider`
  - `display_name`
  - `latitude`
  - `longitude`
  - `importance`
  - `hint_address`
  - `query_source`
  - `query_address`

## Konfigurasi Environment
Variabel yang dipakai:
- `NOMINATIM_ENABLED`
- `NOMINATIM_BASE_URL`
- `NOMINATIM_COUNTRYCODES`
- `NOMINATIM_TIMEOUT`
- `NOMINATIM_CONTACT`
- `APP_URL`
- `APP_NAME`

## Error Handling
- Kegagalan external geocoding tidak memutus alur internal.
- Jika request external gagal/timeout/invalid payload, sistem tetap mengembalikan hasil internal.
- Jika tidak ada kandidat mapping, output status menjadi `unmatched` dengan confidence rendah.

## Catatan Operasional
- Kualitas mapping sangat dipengaruhi kualitas data master wilayah dan alias.
- Top candidates review sebaiknya dipakai untuk workflow verifikasi manual data ambigu.
- Threshold confidence dan bobot komponen dapat dituning berdasarkan dataset berlabel.
