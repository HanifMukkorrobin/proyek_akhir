# Narasi Sidang Akhir GeoVisit PJJ IT

- Deck sumber: `/Users/tra-mac-020423/Downloads/GeoVisit PJJ IT.pptx`
- Jumlah slide: 13
- Project: GeoVisit PJJ IT
- Judul: Implementasi Visualisasi Persebaran Data Menggunakan Peta 3D dan Simulasi Rute Efisien Visitasi Dosen ke Rumah Mahasiswa
- Studi kasus: Persebaran domisili mahasiswa PJJ IT PENS Angkatan 2023
- Catatan: Narasi ini disusun berdasarkan isi slide, kondisi repository, dan ringkasan progress project.

## Pembukaan Umum

Assalamu'alaikum warahmatullahi wabarakatuh.

Selamat pagi/siang Bapak dan Ibu dosen penguji serta dosen pembimbing. Perkenalkan, nama saya Hanif Mukkorrobin dengan NRP 3123510634. Pada kesempatan sidang akhir ini, saya akan mempresentasikan proyek akhir saya yang berjudul "Implementasi Visualisasi Persebaran Data Menggunakan Peta 3D dan Simulasi Rute Efisien Visitasi Dosen ke Rumah Mahasiswa" dengan studi kasus persebaran domisili mahasiswa PJJ IT PENS Angkatan 2023.

Secara umum, project ini berfokus pada bagaimana data alamat mahasiswa yang semula berbentuk teks atau tabel dapat diolah menjadi informasi spasial yang lebih mudah dianalisis melalui dashboard, peta 3D, dan fondasi perencanaan rute visitasi dosen.

## Slide 1 - Pembuka

### Fokus Slide

- Memperkenalkan judul proyek.
- Memperkenalkan identitas penyaji.
- Menyampaikan konteks umum penelitian.

### Narasi

Pada slide pertama ini saya memperkenalkan topik proyek akhir yang saya kerjakan, yaitu GeoVisit PJJ IT. Project ini mengambil studi kasus persebaran domisili mahasiswa PJJ IT PENS Angkatan 2023.

Latar utama dari project ini adalah kebutuhan untuk melihat data domisili mahasiswa secara lebih informatif. Data mahasiswa PJJ memiliki karakteristik lokasi yang tersebar, sehingga jika hanya ditampilkan dalam bentuk tabel, informasi spasialnya sulit dibaca. Oleh karena itu, saya membangun sistem yang menggabungkan pengolahan alamat, dashboard statistik, visualisasi peta 3D, serta fondasi simulasi rute visitasi dosen.

Pada project ini, saya dibimbing oleh Ibu Nana Ramadijanti, S.Kom, M.Kom sebagai dosen pembimbing pertama dan Bapak Nur Rosyid Mubtadai, S.Kom., M.T. sebagai dosen pembimbing kedua.

### Kalimat Penghubung

Sebelum masuk ke bagian teknis sistem, saya akan menjelaskan terlebih dahulu latar belakang mengapa project ini diperlukan.

## Slide 2 - Latar Belakang Proyek

### Fokus Slide

- Menjelaskan sebaran domisili mahasiswa PJJ.
- Menjelaskan keterbatasan data tabular.
- Menjelaskan kebutuhan visitasi dosen berbasis data lokasi.

### Narasi

Mahasiswa PJJ IT PENS Angkatan 2023 memiliki domisili yang tersebar di berbagai wilayah Indonesia. Kondisi ini membuat data domisili memiliki nilai penting, karena dapat menunjukkan bagaimana distribusi mahasiswa berdasarkan wilayah administratif.

Permasalahan muncul ketika data tersebut hanya tersedia dalam bentuk tabel. Tabel memang dapat menyimpan informasi nama dan alamat mahasiswa, tetapi tidak cukup efektif untuk membaca pola spasial, misalnya wilayah mana yang memiliki konsentrasi mahasiswa tinggi, wilayah mana yang masih jarang, atau bagaimana sebaran mahasiswa jika dilihat dari level provinsi sampai desa.

Selain itu, dalam konteks kegiatan akademik, data lokasi mahasiswa juga dapat mendukung rencana visitasi dosen ke rumah mahasiswa. Agar visitasi dapat direncanakan dengan lebih baik, diperlukan data lokasi yang terstruktur, tervalidasi, dan dapat divisualisasikan secara interaktif.

### Kalimat Penghubung

Dari latar belakang tersebut, terdapat beberapa permasalahan utama yang menjadi dasar pengembangan sistem ini.

## Slide 3 - Identifikasi Permasalahan

### Fokus Slide

- Menjelaskan masalah data alamat.
- Menjelaskan masalah visualisasi.
- Menjelaskan kebutuhan manajemen terpadu.
- Menjelaskan gap simulasi rute visitasi.

### Narasi

Permasalahan pertama adalah data alamat mahasiswa yang tidak selalu seragam. Dalam praktiknya, alamat dapat memiliki format penulisan yang berbeda-beda, ada yang lengkap, ada yang hanya mencantumkan sebagian informasi, dan ada pula yang sulit langsung diubah menjadi koordinat.

Permasalahan kedua adalah visualisasi yang masih terbatas. Jika data hanya ditampilkan dalam bentuk tabel, pengguna tidak dapat memahami persebaran mahasiswa secara spasial dan interaktif. Padahal, informasi seperti distribusi per provinsi, kabupaten/kota, kecamatan, hingga desa sangat penting untuk membaca pola domisili.

Permasalahan ketiga adalah kebutuhan sistem yang terpadu. Sistem tidak hanya perlu menampilkan peta, tetapi juga membutuhkan pengelolaan data mahasiswa, wilayah, user, import data, dashboard, dan log aktivitas.

Permasalahan keempat adalah kebutuhan simulasi rute visitasi dosen. Sampai saat ini, aturan bisnis rute seperti titik awal, jumlah kunjungan maksimal, prioritas mahasiswa, dan objektif optimasi masih perlu dirumuskan. Oleh karena itu, pada project ini simulasi rute diposisikan sebagai fondasi yang memanfaatkan data lokasi mahasiswa yang sudah diproses.

### Kalimat Penghubung

Berdasarkan permasalahan tersebut, solusi yang saya tawarkan adalah membangun sistem GeoVisit PJJ IT.

## Slide 4 - Solusi GeoVisit PJJ IT

### Fokus Slide

- Menjelaskan konsep solusi.
- Menjelaskan dashboard admin dan non-admin.
- Menjelaskan geocoding internal dan fallback.
- Menjelaskan peta 3D.
- Menjelaskan fondasi simulasi rute.

### Narasi

GeoVisit PJJ IT merupakan aplikasi web GIS yang dirancang untuk mengolah, mengelola, dan memvisualisasikan data domisili mahasiswa PJJ IT PENS Angkatan 2023.

Solusi pertama adalah dashboard admin dan non-admin. Dashboard admin digunakan untuk memantau data, mengelola mahasiswa, wilayah, user, dan melihat log aktivitas. Dashboard non-admin digunakan oleh dosen atau mahasiswa untuk melihat persebaran data tanpa mengelola data master.

Solusi kedua adalah pipeline geocoding. Sistem melakukan normalisasi alamat, tokenisasi, pencocokan dengan kamus wilayah internal, fuzzy matching, validasi hierarki wilayah, dan fallback ke OpenStreetMap Nominatim jika diperlukan. Dengan alur ini, alamat mahasiswa dapat dikaitkan dengan wilayah dan koordinat.

Solusi ketiga adalah visualisasi peta 3D menggunakan CesiumJS. Peta ini menampilkan marker wilayah dan mahasiswa, mendukung drilldown wilayah, pencarian mahasiswa, dan fokus kamera ke titik tertentu.

Solusi keempat adalah fondasi simulasi rute visitasi. Sistem sudah menyiapkan basis data lokasi dan rancangan konseptual untuk menentukan rute kunjungan dosen berdasarkan titik awal, daftar mahasiswa tujuan, jarak, dan waktu tempuh.

### Kalimat Penghubung

Agar pembahasan tetap terarah, project ini memiliki beberapa batasan masalah yang perlu dijelaskan.

## Slide 5 - Batasan Masalah

### Fokus Slide

- Menjelaskan batas data.
- Menjelaskan batas visualisasi peta.
- Menjelaskan batas simulasi rute.

### Narasi

Batasan pertama adalah data yang digunakan. Project ini dibatasi pada data mahasiswa PJJ IT PENS Angkatan 2023 dan data wilayah administratif yang tersedia di basis data project.

Batasan kedua adalah ruang lingkup visualisasi peta 3D. Sistem berfokus pada marker wilayah dan marker mahasiswa, bukan pada model bangunan 3D, detail topografi lokal, atau analisis spasial tingkat lanjut.

Batasan ketiga adalah simulasi rute. Pembahasan rute dibatasi pada perencanaan dan simulasi berbasis koordinat, jarak, dan waktu yang tersedia. Aturan optimasi final, seperti metode optimasi, prioritas kunjungan, batas waktu, dan kontrak output rute, masih menjadi bagian pengembangan lanjutan.

Selain itu, geocoding eksternal tidak dijadikan sumber utama. Sistem mengutamakan kamus wilayah internal, sedangkan Nominatim hanya dipakai sebagai fallback opsional karena penggunaan eksternal untuk bulk import memiliki risiko performa dan rate limit.

### Kalimat Penghubung

Setelah mengetahui ruang lingkup sistem, berikutnya saya akan menjelaskan rancangan aliran data melalui Data Flow Diagram.

## Slide 6 - Data Flow Diagram

### Fokus Slide

- Menjelaskan fungsi DFD.
- Menjelaskan aktor dan alur data utama.
- Menghubungkan DFD dengan proses sistem.

### Narasi

Data Flow Diagram atau DFD digunakan untuk menggambarkan bagaimana data bergerak di dalam sistem. Pada project ini, DFD menjelaskan hubungan antara aktor, proses, data store, dan layanan eksternal.

Secara umum, aktor yang berinteraksi dengan sistem adalah Admin, Dosen, dan Mahasiswa. Admin mengirim data login, mengelola data mahasiswa, wilayah, user, serta melakukan import data. Dosen mengakses dashboard, peta 3D, pencarian mahasiswa, dan rencana visitasi. Mahasiswa dapat mengakses dashboard dan peta sesuai hak akses.

Di dalam sistem terdapat beberapa proses utama, yaitu autentikasi, manajemen data master, klasifikasi alamat dan geocoding, dashboard persebaran, peta 3D, simulasi rute visitasi, dan log aktivitas.

Untuk layanan eksternal, sistem dapat menggunakan Nominatim sebagai fallback geocoding dan OSRM atau layanan routing sebagai kandidat sumber estimasi jarak dan waktu untuk simulasi rute.

### Kalimat Penghubung

Setelah aliran data dijelaskan melalui DFD, berikutnya saya akan menjelaskan struktur data yang digunakan melalui ERD.

## Slide 7 - Entity Relationship Diagram

### Fokus Slide

- Menjelaskan struktur basis data.
- Menjelaskan entitas inti.
- Menjelaskan kebutuhan data untuk simulasi rute.

### Narasi

Entity Relationship Diagram atau ERD digunakan untuk menggambarkan struktur data dan relasi antarentitas di dalam sistem. Pada project ini, basis data utama menggunakan PostgreSQL.

Entitas inti sistem meliputi `mahasiswa`, `wilayah`, `users`, `usergroups`, `auth_tokens`, dan `activity_logs`. Entitas `mahasiswa` menyimpan data mahasiswa, alamat, wilayah, latitude, dan longitude. Entitas `wilayah` menyimpan data wilayah administratif yang digunakan sebagai referensi geocoding dan agregasi dashboard.

Entitas `users`, `usergroups`, dan `auth_tokens` digunakan untuk autentikasi dan otorisasi. Dengan struktur ini, sistem dapat membedakan hak akses admin, dosen, dan mahasiswa. Entitas `activity_logs` digunakan untuk mencatat aktivitas penting seperti login, akses endpoint, dan pengelolaan data.

Untuk kebutuhan simulasi rute visitasi, rancangan data yang disarankan mencakup `visitasi_rencana`, `visitasi_peserta`, `visitasi_rute`, dan `visitasi_rute_detail`. Entitas ini diperlukan agar sistem dapat menyimpan rencana visitasi, daftar mahasiswa tujuan, hasil rute, dan detail urutan kunjungan.

### Kalimat Penghubung

Setelah struktur data dijelaskan, berikutnya saya akan menjelaskan fungsionalitas sistem dari sudut pandang pengguna melalui use case diagram.

## Slide 8 - Use Case Diagram

### Fokus Slide

- Menjelaskan aktor sistem.
- Menjelaskan fungsi utama admin.
- Menjelaskan fungsi dosen dan mahasiswa.
- Menjelaskan use case simulasi rute.

### Narasi

Use case diagram digunakan untuk menggambarkan interaksi pengguna dengan sistem. Pada project ini terdapat tiga aktor utama, yaitu Admin, Dosen, dan Mahasiswa.

Admin memiliki hak akses untuk login, melihat dashboard admin, mengelola mahasiswa, melakukan import mahasiswa, mengelola wilayah, mengelola user, dan melihat log aktivitas.

Dosen berperan sebagai pengguna non-admin yang dapat melihat dashboard persebaran, membuka peta 3D, mencari mahasiswa, menelusuri wilayah, dan menggunakan data lokasi sebagai dasar perencanaan visitasi.

Mahasiswa juga dapat login dan melihat informasi persebaran melalui dashboard dan peta 3D sesuai hak akses yang diberikan.

Untuk simulasi rute, use case yang dirancang mencakup pemilihan mahasiswa tujuan, penentuan titik awal dosen, proses simulasi rute, dan penampilan hasil rute pada peta 3D. Pada tahap project saat ini, bagian ini masih berupa fondasi rancangan dan belum menjadi service final.

### Kalimat Penghubung

Setelah rancangan fungsional dijelaskan, berikutnya saya masuk ke bagian implementasi frontend yang menjadi antarmuka utama pengguna.

## Slide 9 - Implementasi Frontend

### Fokus Slide

- Menjelaskan teknologi frontend.
- Menjelaskan UI design.
- Menjelaskan dashboard dan integrasi API.

### Narasi

Pada sisi frontend, sistem dibangun menggunakan Nuxt 4 berbasis Vue.js. Untuk styling, sistem menggunakan Tailwind CSS dan DaisyUI, sehingga komponen UI dapat dibuat responsif dan konsisten.

State dan autentikasi dibantu dengan Pinia, sedangkan komunikasi ke backend menggunakan Axios. Untuk visualisasi grafik, dashboard menggunakan Highcharts. Selain itu, sistem juga menggunakan Iconify agar penggunaan ikon lebih konsisten.

Frontend memiliki beberapa halaman utama, yaitu landing page, login, dashboard admin, data master, manajemen wilayah, direktori mahasiswa, manajemen user, log aktivitas, dashboard chart non-admin, dan dashboard map.

Sistem juga mendukung theme light dan dark yang disimpan melalui LocalStorage. Dengan demikian, preferensi tampilan pengguna dapat dipertahankan saat aplikasi digunakan kembali.

### Kalimat Penghubung

Salah satu bagian utama dari frontend adalah peta 3D, sehingga pada slide berikutnya saya akan menjelaskan implementasi CesiumJS.

## Slide 10 - Visualisasi Peta 3D CesiumJS

### Fokus Slide

- Menjelaskan peran CesiumJS.
- Menjelaskan marker wilayah dan mahasiswa.
- Menjelaskan drilldown dan fokus kamera.
- Menjelaskan optimasi rendering.

### Narasi

Visualisasi peta 3D pada sistem ini dibangun menggunakan CesiumJS. CesiumJS dipilih karena mampu menampilkan globe 3D secara interaktif di browser dan mendukung visualisasi data geospasial.

Pada peta 3D, sistem menampilkan marker wilayah dan marker mahasiswa berdasarkan data dari endpoint backend `/dashboard/map/*`. Pengguna dapat melakukan pencarian mahasiswa, melihat marker wilayah, dan melakukan drilldown dari level provinsi ke level administratif yang lebih kecil.

Fitur fokus kamera digunakan agar saat wilayah atau mahasiswa dipilih, kamera dapat berpindah ke lokasi yang relevan. Sistem juga menerapkan penempatan marker relatif terhadap terrain height sehingga marker lebih sesuai dengan permukaan peta.

Dari sisi performa, rendering dioptimasi dengan primitive collection, pembatasan jumlah marker per level, request-render mode, payload caching, serta label fading agar tampilan tetap ringan ketika data wilayah cukup banyak.

### Kalimat Penghubung

Setelah membahas implementasi utama, berikutnya saya akan menjelaskan status ringkas progress project sampai tahap saat ini.

## Slide 11 - Status Ringkas Implementasi

### Fokus Slide

- Menyampaikan progress project.
- Memisahkan fitur yang sudah selesai dan yang masih menjadi pengembangan lanjutan.

### Narasi

Pada tahap saat ini, beberapa bagian utama sistem sudah selesai diimplementasikan. Backend foundation menggunakan Laravel Lumen sudah tersedia, termasuk endpoint wilayah, mahasiswa, user, dashboard, activity log, autentikasi, dan peta 3D.

Pipeline klasifikasi alamat dan geocoding juga sudah tersedia. Sistem dapat melakukan normalisasi alamat, pencocokan wilayah, validasi hierarki, serta menandai data yang bermasalah menggunakan `is_valid_address` dan `geocoding_status`.

Pada sisi frontend, halaman landing, login, dashboard admin, data master, manajemen mahasiswa, manajemen user, log aktivitas, dashboard chart, dan dashboard map sudah tersedia. Dashboard sudah terhubung dengan API, termasuk summary, chart, wilayah tree, dan data peta.

Fitur activity log juga sudah berjalan untuk mencatat aktivitas penting, termasuk login dan request API. Selain itu, data lokasi bermasalah sudah difilter dari statistik dan peta non-admin agar tidak memengaruhi visualisasi utama.

Adapun bagian yang belum selesai adalah simulasi rute visitasi dosen. Saat ini simulasi rute sudah masuk ke rancangan use case, ERD, dan DFD, tetapi service, endpoint, metode optimasi, dan aturan bisnisnya masih perlu didefinisikan lebih lanjut.

### Kalimat Penghubung

Untuk memperkuat dasar teori dan referensi yang digunakan, slide berikutnya menampilkan daftar pustaka.

## Slide 12 - Daftar Pustaka

### Fokus Slide

- Menjelaskan referensi yang mendukung project.
- Menunjukkan dasar teori geocoding, GIS, dan visualisasi 3D.

### Narasi

Pada slide ini ditampilkan daftar pustaka yang menjadi referensi dalam penyusunan project. Referensi yang digunakan mencakup data preprocessing, CesiumJS, konsep 3D GIS, serta penelitian terdahulu terkait sistem informasi geografis.

Referensi data preprocessing digunakan untuk mendukung proses pembersihan dan normalisasi alamat. Referensi CesiumJS dan 3D GIS digunakan sebagai dasar implementasi peta 3D. Sementara itu, penelitian terdahulu digunakan sebagai pembanding terhadap sistem GIS berbasis web yang pernah dikembangkan sebelumnya.

Dengan adanya referensi tersebut, project ini tidak hanya berangkat dari kebutuhan implementasi, tetapi juga didukung oleh dasar teori dan studi terdahulu yang relevan.

### Kalimat Penghubung

Setelah seluruh bagian utama project dijelaskan, saya masuk ke slide penutup.

## Slide 13 - Penutup

### Fokus Slide

- Mengakhiri presentasi.
- Mengucapkan terima kasih.
- Membuka sesi pertanyaan.

### Narasi

Demikian presentasi project akhir saya mengenai GeoVisit PJJ IT. Secara keseluruhan, sistem ini dibangun untuk membantu pengolahan dan visualisasi data domisili mahasiswa PJJ IT PENS Angkatan 2023 melalui dashboard, peta 3D, dan fondasi perencanaan rute visitasi dosen.

Kontribusi utama dari project ini adalah integrasi antara manajemen data mahasiswa, klasifikasi alamat, geocoding, visualisasi persebaran, peta 3D, autentikasi multi-role, log aktivitas, dan rancangan awal simulasi rute visitasi.

Saya menyadari bahwa masih terdapat ruang pengembangan, khususnya pada bagian simulasi rute visitasi dosen, pengujian otomatis, peningkatan akurasi klasifikasi alamat, serta kesiapan konfigurasi production.

Terima kasih atas perhatian Bapak dan Ibu. Saya membuka sesi pertanyaan, masukan, dan diskusi.

## Penutup Alternatif Singkat

Sekian presentasi dari saya. Semoga sistem GeoVisit PJJ IT ini dapat menjadi dasar pengembangan visualisasi data domisili mahasiswa dan perencanaan visitasi dosen yang lebih terstruktur. Terima kasih.

## Catatan Penyampaian

- Gunakan nada formal dan stabil.
- Jangan menyatakan simulasi rute sudah selesai secara penuh; sampaikan sebagai fondasi atau rancangan lanjutan.
- Tekankan bahwa fitur yang sudah berjalan adalah manajemen data, geocoding, dashboard, peta 3D, dan activity log.
- Saat menjelaskan DFD, ERD, dan Use Case, fokus pada fungsi diagram, bukan membaca semua entitas satu per satu.
- Saat demo atau tanya jawab, siapkan halaman berikut:
  - `/`
  - `/auth/login`
  - `/admin/dashboard`
  - `/admin/mahasiswa`
  - `/admin/users`
  - `/admin/log`
  - `/dashboard/chart`
  - `/dashboard/map`
