# Ringkasan Progress Project

- Project: GeoVisit PJJ IT
- Judul: Implementasi Visualisasi Persebaran Data Menggunakan Peta 3D dan Simulasi Rute Efisien Visitasi Dosen ke Rumah Mahasiswa.
- Studi kasus: Persebaran domisili mahasiswa PJJ IT PENS Angkatan 2023.
- Last updated: 2026-06-10
- Sumber ringkasan: repository state, `PROJECT_STATUS.md`, `TASKS.md`, dan hasil pembahasan dokumentasi pada sesi sebelumnya.

## Latar Belakang

- Mahasiswa PJJ IT PENS Angkatan 2023 memiliki domisili yang tersebar di berbagai wilayah Indonesia.
- Data domisili yang hanya ditampilkan dalam bentuk tabel sulit dipahami secara spasial.
- Pihak kampus membutuhkan media visual untuk membaca konsentrasi mahasiswa berdasarkan wilayah.
- Data alamat mahasiswa perlu dibersihkan, diklasifikasikan, dan dipetakan ke koordinat agar dapat digunakan pada dashboard dan peta.
- Rencana visitasi dosen ke rumah mahasiswa membutuhkan dasar data lokasi yang lebih terstruktur.
- Peta 3D dipilih karena dapat memberikan representasi sebaran lokasi yang lebih interaktif dibanding tabel atau peta statis.

## Permasalahan

- Data alamat mahasiswa tidak selalu seragam, lengkap, atau langsung dapat diubah menjadi koordinat.
- Sistem awal belum menyediakan eksplorasi persebaran domisili mahasiswa secara interaktif.
- Data tabel tidak cukup untuk menunjukkan konsentrasi mahasiswa pada level provinsi, kabupaten/kota, kecamatan, atau desa.
- Belum tersedia alur terpadu untuk manajemen mahasiswa, wilayah, user, import data, log aktivitas, dashboard, dan peta 3D.
- Data alamat yang gagal diklasifikasikan dapat mengganggu akurasi statistik dan visualisasi jika tidak ditandai dengan jelas.
- Simulasi rute visitasi dosen sudah memiliki MVP berbasis OSRM, tetapi aturan lanjutan seperti batas kunjungan, durasi visitasi, prioritas, dan jadwal dosen belum final.
- Ketergantungan pada geocoding eksternal tidak cocok untuk bulk import besar tanpa cache atau provider yang mendukung batch processing.

## Solusi

- Membangun aplikasi web GeoVisit PJJ IT dengan frontend Nuxt 4 dan backend Laravel Lumen.
- Menyediakan REST API untuk autentikasi, manajemen mahasiswa, wilayah, user, dashboard, peta 3D, import data, dan log aktivitas.
- Menggunakan PostgreSQL sebagai basis data utama.
- Mengimplementasikan klasifikasi alamat berbasis kamus wilayah internal, normalisasi teks, tokenisasi, fuzzy matching, alias wilayah, dan validasi hierarki wilayah.
- Menambahkan fallback OpenStreetMap Nominatim secara opsional ketika hasil internal tidak cukup kuat.
- Menyediakan fitur import mahasiswa dua tahap: scan/validasi lalu confirm insert.
- Menampilkan dashboard admin dengan summary card, grafik distribusi, dan tabel hierarki wilayah.
- Menampilkan dashboard non-admin untuk dosen/mahasiswa dengan ringkasan, grafik, dan hierarki wilayah.
- Menyediakan peta 3D berbasis CesiumJS untuk marker wilayah, marker mahasiswa, pencarian, drilldown, dan fokus kamera.
- Menandai data lokasi bermasalah menggunakan `is_valid_address` dan `geocoding_status` agar tidak mencemari statistik non-admin.
- Menyediakan simulasi rute visitasi dosen berbasis OSRM, termasuk schema visitasi, modal pembuatan simulasi, sidebar riwayat/detail/hapus, dan rendering polyline pada peta 3D.
- Simulasi rute default menghitung jalur tertutup: titik keberangkatan, mahasiswa terpilih, lalu kembali ke titik keberangkatan.
- Simulasi rute membandingkan urutan input dan urutan optimasi OSRM berdasarkan jarak serta waktu tempuh.

## Batasan Masalah

- Data utama dibatasi pada mahasiswa PJJ IT PENS Angkatan 2023.
- Visualisasi berfokus pada domisili mahasiswa dan agregasi wilayah administratif.
- Peta 3D tidak membahas model bangunan 3D, detail topografi lokal, atau analisis spasial tingkat lanjut.
- Geocoding internal menjadi sumber utama; Nominatim hanya fallback opsional.
- Data alamat kosong atau ambigu ditandai sebagai perlu review, bukan dipaksakan menjadi lokasi valid.
- Statistik dan peta non-admin mengecualikan data dengan alamat tidak valid.
- Simulasi rute visitasi tersedia sebagai MVP berbasis koordinat valid mahasiswa dan OSRM.
- Batasan operasional visitasi lanjutan seperti time window, prioritas, dan kapasitas harian belum diterapkan.
- Kredensial dan konfigurasi production belum final.

## Progress Implementasi

### Shared Context dan Dokumentasi

- `AGENTS.md`, `PROJECT_STATUS.md`, `TASKS.md`, `docs/decisions.md`, dan template handoff sudah tersedia.
- Alur geocoding sudah didokumentasikan pada `docs/alur-geocoding.md`.
- Dokumen SPPA/laporan telah disesuaikan agar mengikuti scope GeoVisit PJJ IT.
- Script Mermaid untuk ERD, use case, DFD Level 0, dan DFD Level 1 telah disiapkan pada sesi dokumentasi.
- Screenshot halaman utama project sudah dibuat di `outputs/screenshots/geovisit-pages/`.

### Backend API

- Laravel Lumen 10 sudah tersedia pada folder `api/`.
- PostgreSQL lokal `project_ta` digunakan sebagai basis data kerja.
- Endpoint publik `/public/get-wilayah` tersedia untuk membaca data wilayah.
- Endpoint uji klasifikasi alamat `/public/test-klasifikasi-alamat` tersedia.
- Endpoint protected memakai middleware `auth.token`.
- Response API aktif distandarkan dengan envelope `code`, `data`, `message`, dan `errors`.
- Pagination helper reusable sudah diterapkan pada endpoint list.
- Endpoint protected `/visitasi/simulasi-rute` tersedia untuk menghitung, menyimpan, melihat daftar, membuka detail, dan menghapus simulasi rute OSRM milik user.

### Autentikasi dan Role

- Login token-based tersedia melalui `/auth/login`.
- Role sudah dinormalisasi ke tabel `usergroups`.
- Role utama: `admin`, `dosen`, dan `mahasiswa`.
- Seeder akun demo memakai password default `P@ssw0rd`.
- Login response tetap mengembalikan alias `role` agar frontend mudah mengarahkan user.

### Manajemen Mahasiswa

- CRUD mahasiswa sudah tersedia melalui endpoint protected `/mahasiswa`.
- Create/update mahasiswa otomatis menjalankan klasifikasi alamat dan geocoding.
- `mahasiswa_id` dibuat sebagai UUID oleh sistem.
- Response mahasiswa sudah menyertakan objek `wilayah`.
- Import mahasiswa mendukung flow scan lalu confirm.
- Template import Excel tersedia.
- Import template hanya membutuhkan kolom `nama` dan `alamat`.
- Weak/empty address tidak lagi dipaksa ke alamat default; data ditandai untuk review.

### Klasifikasi Alamat dan Geocoding

- Pipeline klasifikasi alamat sudah mendukung normalisasi uppercase, pembersihan karakter, stop-word removal, tokenisasi, exact match, contains match, fuzzy match, dan alias wilayah.
- Output klasifikasi dapat mencapai level desa jika data cukup kuat.
- Kandidat ambigu ditandai dengan `needs_confirmation` atau `needs_review`.
- Numeric token sudah dibuang untuk mengurangi noise alamat.
- Soft-deleted wilayah tidak dipakai sebagai anchor kandidat utama.
- Hint administratif provinsi/kabupaten/kecamatan/desa dipakai untuk memperkuat ranking kandidat.
- Nominatim fallback bersifat opsional dan tidak aktif secara default untuk bulk import.
- Fallback koordinat PENS tersedia untuk data yang tidak valid dengan flag `is_valid_address=false`.

### Manajemen Wilayah

- CRUD wilayah tersedia melalui endpoint protected `/wilayah`.
- Data wilayah digunakan sebagai referensi hierarki administratif, klasifikasi alamat, agregasi dashboard, dan marker peta.
- Frontend admin memiliki halaman `Data Master` dan `Manajemen Wilayah`.

### Manajemen User

- User CRUD tersedia melalui endpoint protected `/users`.
- Frontend admin `Manajemen User` sudah terhubung ke API.
- Fitur reset password tersedia.
- User dapat dikaitkan dengan usergroup dan opsional dengan mahasiswa.

### Dashboard dan Statistik

- Endpoint dashboard tersedia untuk summary, chart, dan wilayah tree.
- Admin dashboard sudah memakai summary card, Highcharts, dan tabel hierarki wilayah.
- Dashboard non-admin tersedia di `/dashboard/chart`.
- Data lokasi tidak valid difilter dari statistik dan tampilan non-admin.
- Admin dashboard menampilkan card `Data Lokasi Bermasalah` untuk kebutuhan review.
- Peta 3D dapat membuat simulasi OSRM dari modal, memilih mahasiswa tujuan, memilih titik berangkat/kendaraan, menyimpan hasil, membuka/menghapus riwayat simulasi, dan menggambar geometry sebagai polyline.

### Activity Log

- Tabel, model, repository, controller, dan middleware activity log sudah tersedia.
- Login success/failure dicatat tanpa menyimpan password atau bearer token.
- Admin dapat melihat log aktivitas melalui `/admin/log`.
- `/admin/log-simulasi` saat ini redirect ke log aktivitas umum.

### Frontend

- Nuxt 4 tersedia di folder `app/`.
- Tailwind CSS, DaisyUI, Pinia, Axios, Iconify, Highcharts, dan CesiumJS sudah terpasang.
- Landing page, login, admin dashboard, data master, mahasiswa, user, log, dashboard chart, dan dashboard map sudah tersedia.
- Theme light/dark sudah tersedia dan tersimpan melalui localStorage.
- Visual system sudah disesuaikan dengan identitas GeoVisit PJJ IT.
- Production build pernah divalidasi berhasil dengan workaround nonaktif `cssnano`.

### Peta 3D

- Peta 3D berbasis CesiumJS tersedia pada dashboard map.
- Data peta mengambil marker dari endpoint `/dashboard/map/*`.
- Fitur peta mencakup marker wilayah, marker mahasiswa, pencarian mahasiswa, drilldown wilayah, label wilayah, zoom controls, dan fokus kamera.
- Cesium dimuat melalui static asset `/cesium/Cesium.js` untuk menghindari masalah import Vite.
- Rendering peta dioptimasi dengan primitive collection, marker limit, request-render mode, payload caching, dan label fading.
- Marker wilayah dan mahasiswa dapat ditempatkan relatif terhadap terrain height.
- Geometry rute OSRM dapat dirender sebagai polyline pada peta 3D.

### Simulasi Rute Visitasi Dosen

- Simulasi rute termasuk dalam scope project dan sudah dimasukkan pada dokumentasi use case, ERD, dan DFD.
- Schema data tersedia melalui `visitasi_rencana`, `visitasi_peserta`, `visitasi_rute`, dan `visitasi_rute_detail`.
- Schema visitasi sudah dinormalisasi agar database lokal dengan kolom legacy tetap kompatibel dengan kontrak simulasi baru.
- Endpoint `POST /visitasi/simulasi-rute` menggunakan OSRM sebagai provider routing.
- Endpoint `GET /visitasi/simulasi-rute` dan `GET /visitasi/simulasi-rute/{simulationId}` tersedia untuk daftar dan detail simulasi tersimpan.
- Endpoint `DELETE /visitasi/simulasi-rute/{simulationId}` tersedia untuk menghapus simulasi tersimpan milik user yang sedang login.
- OSRM `trip` dipakai untuk optimasi urutan waypoint; OSRM `route` tersedia untuk urutan input tetap.
- Request OSRM memakai `steps=true`, `geometries=geojson`, `overview=full`, dan `annotations=duration,distance`.
- Response backend mengembalikan ordered waypoints, ordered mahasiswa, route geometry, legs, leg summaries, steps, dan raw OSRM response.
- Jika request tidak mengirim `titik_akhir`, backend memakai titik keberangkatan sebagai waypoint akhir agar geometry dan legs mencakup perjalanan pulang.
- Response backend juga mengembalikan kandidat rute manual vs optimasi, skor efektivitas, rekomendasi, dan penghematan jarak/waktu.
- Frontend peta 3D menyediakan tombol `Buat Simulasi`, modal input judul/deskripsi/mahasiswa/titik berangkat/kendaraan, dan sidebar riwayat/detail/hapus simulasi.
- Frontend peta 3D dapat menggambar geometry OSRM dari kandidat terbaik sebagai polyline dan menampilkan perbandingan jarak/waktu di detail simulasi.
- Business rule lanjutan belum final: time window, prioritas visitasi, batas kunjungan per hari, pengecualian titik akhir eksplisit, dan strategi production OSRM.

## Route Halaman Frontend

- `/`
- `/auth/login`
- `/admin`
- `/admin/dashboard`
- `/admin/data-master`
- `/admin/data-master/wilayah`
- `/admin/mahasiswa`
- `/admin/users`
- `/admin/log`
- `/admin/log-simulasi`
- `/dashboard/chart`
- `/dashboard/map`

## Progress Screenshot

- Screenshot halaman utama sudah dibuat untuk landing, login, admin dashboard, data master, manajemen wilayah, direktori mahasiswa, manajemen user, log aktivitas, dashboard chart, dan dashboard map.
- Screenshot tersimpan pada `outputs/screenshots/geovisit-pages/`.
- Capture dilakukan dengan frontend lokal dan backend lokal menggunakan akun demo admin.

## Sisa Pekerjaan Utama

- Validasi simulasi OSRM memakai data dummy/seed yang disetujui, bukan koordinat mahasiswa privat.
- Menentukan strategi production OSRM: self-hosted OSRM atau provider routing yang disetujui.
- Menambahkan automated test untuk parsing response OSRM, geometry, legs, steps, dan persistence visitasi.
- Menentukan business rule lanjutan untuk time window, prioritas, batas kunjungan, dan pengecualian titik akhir eksplisit.
- Menambahkan automated test untuk wilayah endpoint, classifier, CRUD, dashboard, dan import.
- Menambahkan persistent cache untuk Nominatim jika fallback eksternal tetap digunakan.
- Menambah alias wilayah berbasis data untuk meningkatkan akurasi klasifikasi alamat.
- Meninjau data alamat ambigu dan data lokasi bermasalah.
- Menyusun konfigurasi production untuk database, API base URL, token, dan external service.

## Status Ringkas

- Backend foundation: selesai.
- Auth dan role: selesai.
- Mahasiswa CRUD/import: selesai.
- Wilayah CRUD: selesai.
- User CRUD: selesai.
- Dashboard admin: selesai.
- Dashboard non-admin: selesai.
- Peta 3D: selesai untuk visualisasi persebaran.
- Activity log: selesai.
- Filtering data lokasi bermasalah: selesai.
- Simulasi rute visitasi: alur modal, simpan, riwayat/detail/hapus, rute kembali ke titik awal, pembandingan jarak/waktu, dan rendering OSRM selesai; aturan operasional lanjutan dan production routing masih perlu diputuskan.
