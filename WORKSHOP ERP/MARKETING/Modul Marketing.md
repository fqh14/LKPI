# 📊 Dokumentasi Operasional Modul Sales/Marketing (`SH-SALES/MS`) & Product Executive (`LKPI-PE`)

**Sistem ERP Enterprise Terintegrasi**

Dokumen ini memuat penjabaran mendetail mengenai arsitektur, fitur operasional, dan alur kerja harian untuk dua pilar penting dalam ekspansi pasar dan manajemen produk perusahaan, yaitu **Modul CRM & Sales (`SH-SALES/MS`)** berbasis *Progressive Web App (PWA)* serta **Modul Product Executive (`LKPI-PE`)**.

---

## 📱 1. Modul CRM & Sales (`SH-SALES/MS`) - Field Force Automation & PWA

Modul ini dirancang khusus sebagai aplikasi lapangan (*Mobile-First* / PWA) untuk mendukung mobilitas tim Sales dan Marketing secara *real-time* dan terintegrasi dengan sistem pusat.

### 📍 a. Kehadiran & Pelacakan Lapangan (*Field Tracking*)
* **Presensi Foto & Tag Lokasi:** Tim sales melakukan absen harian dengan validasi foto wajah (*face/photo recognition*) serta koordinat GPS (*geo-tagging*) untuk memastikan kehadiran di titik kunjungan yang sah.
* **Penjadwalan & Realisasi Kunjungan:** Pengelolaan rute kunjungan harian (*daily itinerary*), pencatatan hasil kunjungan (*check-in* dan *check-out*), serta evaluasi tingkat kepatuhan rute (*route compliance*).
* **Kunjungan Luar Kota (*Up Country*):** Mekanisme khusus untuk penjadwalan, pelaporan, dan estimasi durasi kunjungan lintas wilayah yang memerlukan penanganan khusus.

### 💵 b. Keuangan Lapangan, Akomodasi, & Promosi
* **Permintaan Akomodasi:** Pengajuan fasilitas penginapan, tiket, atau transportasi pendukung kunjungan lapangan.
* **Pengajuan & Realisasi Support Dana / UC:** Pengelolaan dana dukungan operasional atau *Uang Cabang/Celengan* (UC) mulai dari pengajuan proposal hingga pertanggungjawaban realisasinya.
* **Diskon Proposal Form (DPP) & Cashback:** Mekanisme pengajuan skema diskon khusus atau *cashback* bagi pelanggan dengan persetujuan berjenjang (*approval workflow*).
* **Advance & Expenses:** Pengelolaan kasbon muka (*advance*) operasional lapangan serta pelaporan pertanggungjawaban pengeluaran riil (*expenses*) beserta lampiran bukti transaksi.

### 🗓️ c. Administrasi, Event, & Pelayanan Pelanggan
* **Pengajuan Cuti & Izin:** Fitur pengajuan ketidakhadiran mandiri langsung dari aplikasi PWA yang terhubung ke sistem HR.
* **Pengajuan, Penjadwalan, & Monitoring Event:** Manajemen siklus acara pemasaran (pameran, *gathering*, atau peluncuran produk) dari tahap perencanaan anggaran hingga pemantauan jalannya acara.
* **Monitoring & Permintaan Support Produk / Sampel:** Fasilitas bagi sales untuk meminta alokasi sampel produk atau materi promosi (*gimmick*) guna mendukung penetrasi pasar.
* **Monitoring & Pengajuan Complaint:** Pencatatan keluhan pelanggan di lapangan untuk diteruskan ke departemen terkait secara transparan dan terpantau status penyelesaiannya.
* **Input & Monitoring Sales Order (SO):** Pemasukan pesanan penjualan langsung dari lapangan oleh sales serta pemantauan status pemrosesan SO hingga pengiriman secara *real-time*.
* **Laporan Penjualan Komprehensif:** Akses pelaporan analitik berbasis area, produk, kategori produk, serta perbandingan antara target dan realisasi penjualan aktual.

---

## 👔 2. Modul Product Executive (`LKPI-PE`) - Strategi, Perencanaan, & Kontrol

Modul ini berfokus pada kendali penuh atas portofolio produk, perencanaan bisnis terintegrasi, manajemen promosi, serta analisis performa komersial oleh tim *Product Executive*.

### 📦 a. Manajemen Material & Distribusi
* **Material Management:** Pengelolaan siklus pengadaan dan distribusi sampel, materi promosi (*gimmick*), serta inventaris pendukung pemasaran produk.
* **Sales & Distribution:** Pengawasan alur distribusi produk dari gudang utama hingga ke tangan konsumen atau mitra ritel.

### 📈 b. Perencanaan Bisnis & Analisis Tingkat Lanjut
* **Advance Planning and Optimization (APO):** Optimasi rantai pasok dan penjadwalan produksi berdasarkan proyeksi permintaan pasar.
* **Integrated Business Planning (IBP):** Penyusunan strategi bisnis jangka panjang yang mencakup infografis analitik, analisis tren pasar, serta peramalan (*forecast*) penjualan masa depan.
* **Trade Promotion Planning & Management:** Perencanaan anggaran promosi perdagangan, evaluasi efektivitas program, serta manajemen mitra promosi.

### 🔍 c. Pengendalian Keuangan & Proyek Internal
* **Controlling (Commitment & Actual):** Pemantauan ketat terhadap komitmen anggaran (*committed budget*) versus pengeluaran aktual (*actual spend*) pada setiap lini produk atau kategori.
* **Travel Management (UpCountry, Expense, Akomodasi, Support Dana):** Pengawasan dan audit pengeluaran perjalanan dinas serta dukungan dana operasional tim produk.
* **Internal Order Report (Commitment vs Actual):** Laporan analitik mendalam untuk melacak penggunaan anggaran berdasarkan nomor *Internal Order* perusahaan guna mencegah pembengkakan biaya.

### 🎯 d. Eksekusi Lapangan & Manajemen Promosi
* **Promotion Management:** Pengelolaan jenis-jenis promosi, periode penawaran, dan kuota diskon secara terpusat.
* **Field Execution & Activity:** Pemantauan aktivitas pemasaran di lapangan untuk memastikan kesesuaian eksekusi dengan panduan merek (*brand guidelines*).
* **Event Planning & Management:** Koordinasi perencanaan acara skala besar yang dikelola langsung di bawah supervisi Product Executive.