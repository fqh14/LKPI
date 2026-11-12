# 📋 SALES/MARKETING & PRODUCT EXECUTIVE

---

## Modul SH-SALES/MS (Sales & Marketing System)

> **Output:** Progressive Web App (PWA)

---

### 1. 📍 CRM & Sales — Presensi Foto & Tag Lokasi

| Komponen | Penjabaran |
|---|---|
| **Presensi Foto** | Sales wajib mengambil foto selfie saat check-in dan check-out di lokasi kunjungan. Foto tersimpan dengan timestamp otomatis. |
| **Tag Lokasi (GPS)** | Sistem menangkap koordinat GPS (latitude & longitude) saat presensi dilakukan. Validasi radius lokasi terhadap titik pelanggan yang dijadwalkan. |
| **Geofencing** | Notifikasi otomatis jika sales berada dalam radius tertentu dari lokasi pelanggan. |
| **Riwayat Presensi** | Log lengkap semua aktivitas presensi: waktu, lokasi, foto, durasi kunjungan. |
| **Validasi Kunjungan** | Sistem membandingkan lokasi presensi dengan database alamat pelanggan untuk memastikan keabsahan kunjungan. |

---

### 2. 📅 Penjadwalan Kunjungan

| Komponen | Penjabaran |
|---|---|
| **Jadwal Harian/Mingguan/Bulanan** | Sales dapat membuat dan melihat jadwal kunjungan dalam berbagai tampilan (harian, mingguan, bulanan). |
| **Route Planning** | Optimasi rute kunjungan berdasarkan lokasi geografis pelanggan untuk efisiensi waktu dan biaya transportasi. |
| **Kategori Kunjungan** | Kunjungan rutin, kunjungan follow-up, kunjungan prospek baru, kunjungan complaint. |
| **Notifikasi & Reminder** | Pengingat otomatis H-1 dan pada hari-H kunjungan via push notification PWA. |
| **Re-scheduling** | Kemampuan mengubah jadwal kunjungan dengan alasan dan persetujuan supervisor. |
| **Kalender Terintegrasi** | Sinkronisasi dengan kalender eksternal (Google Calendar, Outlook). |

---

### 3. ✅ Realisasi Kunjungan

| Komponen | Penjabaran |
|---|---|
| **Check-in / Check-out** | Pencatatan waktu tiba dan waktu selesai di lokasi pelanggan. |
| **Form Kunjungan** | Form digital yang diisi sales setelah kunjungan: hasil diskusi, kebutuhan pelanggan, action plan. |
| **Foto Dokumentasi** | Upload foto kondisi toko/produk/display saat kunjungan. |
| **Status Kunjungan** | Selesai, Dibatalkan, Direschedule, Tidak Hadir — dengan alasan. |
| **Laporan Realisasi** | Perbandingan antara jadwal vs realisasi kunjungan (achievement rate). |
| **KPI Kunjungan** | Target jumlah kunjungan per periode vs realisasi, tingkat kepatuhan (compliance rate). |

---

### 4. 🏙️ Kunjungan Luar Kota (Up Country)

| Komponen | Penjabaran |
|---|---|
| **Pengajuan Up Country** | Form pengajuan kunjungan luar kota: tujuan, durasi, tujuan bisnis, estimasi biaya. |
| **Approval Workflow** | Alur persetujuan bertingkat: Supervisor → Manager → Finance (tergantung budget). |
| **Itinerary** | Rencana perjalanan lengkap: tanggal, kota, pelanggan yang dikunjungi, agenda per hari. |
| **Logistik Perjalanan** | Pengaturan transportasi (tiket pesawat, kereta, rental mobil) dan akomodasi. |
| **Laporan Up Country** | Laporan hasil kunjungan luar kota dengan dokumentasi dan rekomendasi. |
| **Budget Tracking** | Monitoring anggaran perjalanan up country vs realisasi pengeluaran. |

---

### 5. 🏨 Permintaan Akomodasi

| Komponen | Penjabaran |
|---|---|
| **Pengajuan Akomodasi** | Form permintaan booking hotel/penginapan untuk kunjungan luar kota atau event. |
| **Pilihan Akomodasi** | Database hotel/rekomendasi akomodasi dengan rate card perusahaan. |
| **Approval & Booking** | Proses persetujuan dan konfirmasi booking melalui sistem. |
| **Check-in/Check-out Tracking** | Pencatatan tanggal dan durasi menginap. |
| **Rekonsiliasi Biaya** | Perbandingan biaya akomodasi yang diapprove vs biaya aktual. |

---

### 6. 📝 Pengajuan & Realisasi Support Form

| Komponen | Penjabaran |
|---|---|
| **Jenis Support** | Support dana, support produk, support material promosi, support event. |
| **Form Pengajuan** | Detail pengajuan: jenis support, jumlah, tujuan, periode, ROI yang diharapkan. |
| **Approval Matrix** | Workflow persetujuan berdasarkan nilai dan jenis support. |
| **Tracking Status** | Status pengajuan: Draft → Submitted → Under Review → Approved/Rejected → Disbursed. |
| **Realisasi Support** | Laporan penggunaan support: apakah sesuai dengan pengajuan, bukti penggunaan (foto, invoice). |
| **Evaluasi Efektivitas** | Analisis ROI support yang diberikan terhadap peningkatan penjualan. |

---

### 7. 💰 OC, Diskon Proposal Form (DPF), Cashback, Advance, Expenses

#### 7.1 OC (Open Credit)
| Komponen | Penjabaran |
|---|---|
| **Pengajuan OC** | Form pengajuan open kredit/unit untuk pelanggan dengan limit dan tenor tertentu. |
| **Approval & Limit** | Penetapan limit OC per pelanggan berdasarkan history dan credit scoring. |
| **Monitoring Saldo Credit Limit** | Tracking sisa limit, penggunaan, dan jatuh tempo. |

#### 7.2 Diskon Proposal Form (DPF) & Kontrol Harga
| Komponen | Penjabaran |
|---|---|
| **Pengajuan Diskon** | Form pengajuan diskon khusus untuk pelanggan/proyek tertentu di luar diskon standar. |
| **Tingkat Diskon** | Persentase atau nominal diskon yang diajukan, dengan justifikasi bisnis. |
| **Approval Workflow** | Persetujuan bertingkat berdasarkan besaran diskon. |
| **Validasi DPF** | Pengecekan apakah diskon masih dalam margin yang diperbolehkan oleh sistem. |
| **Laporan DPF** | Rekap seluruh DPF yang diajukan, disetujui, dan dampaknya terhadap revenue. |

> 💻 **Technical Insight (Sales Quotation):**
> Sistem secara otomatis mengunci harga dan batas maksimal diskon (*Max Discount*) berdasarkan master data saat sales membuat penawaran (`Salesquotation.php`). Jika diskon melebihi batas, sistem akan otomatis menolak penyimpanan data.

#### 7.3 Cashback
| Komponen | Penjabaran |
|---|---|
| **Program Cashback** | Konfigurasi program cashback: persentase, periode, syarat & ketentuan. |
| **Pengajuan Cashback** | Form klaim cashback oleh sales atau pelanggan. |
| **Validasi & Approval** | Pengecekan eligibility dan persetujuan cashback. |
| **Pencairan Cashback** | Tracking status pencairan: pending, approved, transferred. |
| **Laporan Cashback** | Total cashback yang diberikan per periode, per area, per produk. |

#### 7.4 Advance (Uang Muka / Advance Payment)
| Komponen | Penjabaran |
|---|---|
| **Pengajuan Advance** | Permintaan dana muka untuk kegiatan sales: kunjungan, event, promosi. |
| **Jumlah & Tujuan** | Nominal advance, tujuan penggunaan, periode penggunaan. |
| **Approval & Disbursement** | Proses persetujuan dan pencairan dana. |
| **Pertanggungjawaban** | Sales wajib submit laporan penggunaan advance dengan bukti pengeluaran. |
| **Rekonsiliasi** | Perbandingan advance yang diberikan vs penggunaan aktual (lebih/kurang). |

#### 7.5 Expenses (Pengeluaran)
| Komponen | Penjabaran |
|---|---|
| **Input Expenses** | Pencatatan pengeluaran operasional sales: transportasi, makan, komunikasi, dll. |
| **Kategori Expenses** | Transportasi lokal, transportasi luar kota, akomodasi, konsumsi, entertainment, lainnya. |
| **Upload Bukti** | Upload foto struk/invoice sebagai bukti pengeluaran. |
| **Approval Expenses** | Workflow persetujuan pengeluaran oleh supervisor/finance. |
| **Reimbursement** | Proses penggantian pengeluaran yang disetujui. |
| **Budget vs Actual** | Monitoring anggaran expenses per sales/area vs realisasi. |

---

### 8. 🏖️ Pengajuan Cuti & Izin

| Komponen | Penjabaran |
|---|---|
| **Jenis Cuti** | Cuti tahunan, cuti sakit, cuti besar, cuti melahirkan, izin terlambat, izin tidak masuk. |
| **Form Pengajuan** | Tanggal mulai-selesai, alasan, dokumen pendukung (surat dokter, dll). |
| **Saldo Cuti** | Tracking sisa cuti tahunan per karyawan secara real-time. |
| **Approval Workflow** | Persetujuan oleh supervisor/HRD dengan notifikasi otomatis. |
| **Kalender Cuti Tim** | Visibility cuti seluruh anggota tim untuk perencanaan jadwal kunjungan. |
| **Dampak ke Jadwal** | Otomatis cancel kunjungan yang jatuh pada tanggal cuti sales. |

---

### 9. 🎪 Pengajuan & Penjadwalan Event

| Komponen | Penjabaran |
|---|---|
| **Jenis Event** | Product launch, roadshow, exhibition, seminar, customer gathering, promotional event. |
| **Form Pengajuan Event** | Nama event, tanggal, lokasi, target peserta, budget, tujuan bisnis. |
| **Timeline & Milestone** | Perencanaan timeline persiapan event: pra-event, D-day, post-event. |
| **Resource Allocation** | Alokasi SDM, material, produk sample untuk event. |
| **Approval Budget Event** | Persetujuan anggaran event dengan breakdown biaya. |
| **Kalender Event** | Tampilan kalender semua event yang dijadwalkan per area/periode. |

---

### 10. 🔍 Monitoring Event

| Komponen | Penjabaran |
|---|---|
| **Dashboard Event** | Overview semua event: status, progress, attendance, budget utilization. |
| **Checklist Persiapan** | Monitoring kelengkapan persiapan event per milestone. |
| **Attendance Tracking** | Pencatatan jumlah peserta/kunjungan saat event berlangsung. |
| **Foto & Dokumentasi** | Upload foto kegiatan event sebagai bukti pelaksanaan. |
| **Laporan Post-Event** | Laporan hasil event: jumlah leads, penjualan saat event, feedback peserta. |
| **ROI Event** | Analisis efektivitas event terhadap target penjualan/brand awareness. |

---

### 11. 📊 Laporan Penjualan

#### 11.1 Laporan Penjualan per Area
| Komponen | Penjabaran |
|---|---|
| **Hierarki Area** | Nasional → Regional → Area → Sub-Area → Kota → Outlet. |
| **Metrik** | Revenue, volume penjualan, jumlah transaksi, average transaction value. |
| **Periode** | Harian, mingguan, bulanan, kuartalan, tahunan. |
| **Visualisasi** | Grafik tren, peta heat map, tabel perbandingan antar area. |
| **Drill-down** | Kemampuan drill-down dari level nasional hingga level outlet. |

#### 11.2 Laporan Penjualan per Produk
| Komponen | Penjabaran |
|---|---|
| **Detail Produk** | SKU, nama produk, kategori, harga, margin. |
| **Metrik** | Volume terjual, revenue, growth rate, market share internal. |
| **Ranking** | Top N produk terlaris, bottom N produk dengan performa terendah. |
| **Tren** | Grafik penjualan per produk over time. |

#### 11.3 Laporan Penjualan per Kategori Produk
| Komponen | Penjabaran |
|---|---|
| **Kategorisasi** | Grouping produk berdasarkan kategori (brand, tipe, segmen, harga). |
| **Kontribusi Kategori** | Persentase kontribusi tiap kategori terhadap total penjualan. |
| **Perbandingan** | YoY, MoM growth per kategori. |
| **Insight** | Kategori yang growing vs declining, rekomendasi strategi. |

---

### 12. 🎯 Target & Realisasi Penjualan

| Komponen | Penjabaran |
|---|---|
| **Setting Target** | Penetapan target penjualan per sales, per area, per produk, per periode (bulanan/kuartalan/tahunan). |
| **Breakdown Target** | Target di-breakdown dari level nasional ke level individual sales. |
| **Real-time Tracking** | Dashboard real-time pencapaian target vs aktual. |
| **Achievement Rate** | Persentase pencapaian target dengan indikator warna (merah/kuning/hijau). |
| **Forecast** | Proyeksi pencapaian target di akhir periode berdasarkan tren saat ini. |
| **Alert & Notifikasi** | Peringatan otomatis jika pencapaian di bawah threshold tertentu. |
| **Incentive Calculation** | Perhitungan insentif/bonus berdasarkan achievement rate. |

---

### 13. 📦 Monitoring & Permintaan Support Produk

| Komponen | Penjabaran |
|---|---|
| **Katalog Support Produk** | Database produk yang tersedia untuk support/promosi. |
| **Pengajuan Support** | Form permintaan produk support: jenis, jumlah, tujuan, periode. |
| **Stok Support** | Monitoring ketersediaan stok produk untuk support. |
| **Approval & Distribusi** | Workflow persetujuan dan tracking distribusi produk support. |
| **Laporan Penggunaan** | Tracking penggunaan produk support dan efektivitasnya. |
| **Return & Rekonsiliasi** | Pencatatan produk support yang tidak terpakai dan dikembalikan. |

---

### 14. 🧪 Monitoring & Permintaan Sampel

| Komponen | Penjabaran |
|---|---|
| **Katalog Sampel** | Database sampel produk yang tersedia untuk distribusi ke pelanggan/prospek. |
| **Pengajuan Sampel** | Form permintaan sampel: produk, jumlah, target penerima, tujuan. |
| **Approval Sampel** | Persetujuan berdasarkan kebijakan alokasi sampel. |
| **Tracking Distribusi** | Monitoring distribusi sampel: dikirim, diterima, ditindaklanjuti. |
| **Follow-up Sampel** | Tracking tindak lanjut setelah sampel diberikan (konversi ke order). |
| **Laporan Efektivitas Sampel** | Conversion rate dari sampel menjadi penjualan aktual. |

---

### 15. ⚠️ Monitoring & Pengajuan Complaint

| Komponen | Penjabaran |
|---|---|
| **Input Complaint** | Form pengaduan: jenis complaint (produk, pengiriman, pelayanan), detail, bukti foto. |
| **Kategorisasi** | Complaint produk rusak, expired, salah kirim, kurang jumlah, pelayanan, lainnya. |
| **Severity Level** | Low, Medium, High, Critical — dengan SLA penanganan berbeda. |
| **Workflow Penanganan** | Assign ke tim terkait → Investigasi → Resolusi → Follow-up → Closed. |
| **Tracking Status** | Open, In Progress, Resolved, Closed, Escalated. |
| **SLA Monitoring** | Monitoring waktu respons dan resolusi terhadap SLA yang ditetapkan. |
| **Root Cause Analysis** | Analisis akar masalah complaint untuk pencegahan. |
| **Laporan Complaint** | Statistik complaint per area, per produk, per periode, tren complaint. |

---

### 16. 🛒 Input Sales Order (SO) & Siklus Transaksi

| Komponen | Penjabaran |
|---|---|
| **Input Sales Order** | Form input order langsung dari lapangan: produk, qty, harga, pelanggan, tanggal pengiriman. Wajib ditarik dari *Sales Quotation* (Copy From SQ). |
| **Validasi Stok (Commitment)** | Pengecekan ketersediaan stok riil. Jika SO disetujui, sistem otomatis mem-booking stok (*Committed Stock*) agar tidak dijual ke orang lain. |
| **Validasi Harga** | Pengecekan harga sesuai price list, diskon yang berlaku, DPF yang approved. |
| **Credit Check** | Pengecekan limit kredit/UC pelanggan sebelum order diproses. |
| **Order Status** | Draft, Submitted, Approved, Processing, Shipped, Delivered, Completed, Cancelled. |
| **Multi-channel Order** | Input order via PWA (field sales), telepon, email — semua terintegrasi. |
| **Order History** | Riwayat lengkap semua order per pelanggan dengan detail perubahan. |
| **Integration** | Integrasi dengan modul inventory, finance, dan logistics untuk fulfillment. |

> 💻 **Technical Insight (Sales Order):**
> Saat SO di-Approve (Aktif), sistem memanggil fungsi `addCommitted` untuk "mem-booking" stok di gudang. Jika SO dibatalkan (*Cancel*), alokasi stok tersebut otomatis dikembalikan (`reduceCommitted`), memastikan data inventory selalu akurat.

---

## Modul LKPI-PE (Product Executive)

---

### 1. 📦 Material Management (Sample, Gimmick & Pengadaan)

| Komponen | Penjabaran |
|---|---|
| **Katalog Material** | Database lengkap semua material: sample produk, gimmick, merchandise, material promosi. |
| **Pengadaan Material** | Proses procurement material: requisition, PO, penerimaan, quality check. |
| **Inventory Material** | Tracking stok material: masuk, keluar, saldo, lokasi penyimpanan. |
| **Distribusi Material** | Alokasi dan distribusi material ke area/sales/event. |
| **Sample Management** | Lifecycle management sampel: produksi, distribusi, tracking, conversion rate. |
| **Gimmick Management** | Pengelolaan gimmick/merchandise: desain, produksi, stok, distribusi ke campaign. |
| **Minimum Stock Alert** | Notifikasi otomatis jika stok material di bawah threshold minimum. |
| **Laporan Material** | Penggunaan material per periode, per area, per campaign, efisiensi pengadaan. |

---

### 2. 🚚 Sales & Distribution (Fulfillment & Delivery)

| Komponen | Penjabaran |
|---|---|
| **Sales Channel Management** | Pengelolaan channel distribusi: direct sales, distributor, retailer, e-commerce. |
| **Batch Management (FEFO)** | Pemilihan stok barang keluar berdasarkan *First Expired First Out* (FEFO) agar barang yang masa kedaluwarsanya paling dekat dikeluarkan lebih dulu. |
| **Distribution Planning** | Perencanaan distribusi produk ke seluruh channel dan area. |
| **Order Fulfillment** | Tracking proses pemenuhan dari Sales Order (SO) menjadi Delivery Order (DO). Sistem menarik data otomatis dari SO. |
| **Logistics Coordination** | Koordinasi dengan tim logistik untuk pengiriman tepat waktu. |
| **Delivery Tracking** | Monitoring status pengiriman: dispatched, in-transit, delivered. |
| **Proof of Delivery** | Konfirmasi penerimaan barang dengan tanda tangan/foto digital. |
| **Return Management** | Proses retur barang: alasan, approval, restocking, credit note. |
| **Coverage Analysis** | Analisis cakupan distribusi per area: outlet aktif, outlet potensial, gap analysis. |

> 💻 **Technical Insight (Delivery Order):**
> Product Executive / Tim Logistik sangat terbantu dengan fitur *Auto-Batch Allocation*. Sistem *Delivery Order* (`Deliveryorder.php`) secara otomatis mencari stok barang dengan tanggal kedaluwarsa paling awal (FEFO) untuk dialokasikan ke DO. Sistem juga memvalidasi ketat agar tim gudang tidak mengirim barang melebihi pesanan SO.
> Selain itu, setelah barang dikirim (DO Aktif), sistem otomatis memotong jumlah pesanan yang belum terkirim di SO. Jika barang sudah dikirim semua, SO otomatis berubah status menjadi `Closed` (Selesai).

---

### 3. 📈 Advance Planning and Optimization

| Komponen | Penjabaran |
|---|---|
| **Demand Planning** | Perencanaan permintaan produk berdasarkan historis, tren, dan faktor eksternal. |
| **Supply Planning** | Perencanaan pasokan untuk memenuhi demand yang diproyeksikan. |
| **S&OP Process** | Sales & Operations Planning: alignment antara sales forecast dan kapasitas produksi/supply. |
| **Optimization Engine** | Algoritma optimasi untuk inventory level, distribusi, dan alokasi resource. |
| **Scenario Planning** | Simulasi berbagai skenario (best case, worst case, most likely) untuk perencanaan. |
| **KPI Planning** | Target KPI per periode dengan breakdown per tim/individu. |
| **Resource Optimization** | Optimasi alokasi SDM, budget, dan material untuk efisiensi maksimal. |

---

### 4. 📊 Integrated Business Planning (Infografis, Trend, Forecast)

#### 4.1 Infografis
| Komponen | Penjabaran |
|---|---|
| **Dashboard Eksekutif** | Tampilan visual ringkasan performa bisnis: revenue, growth, market share. |
| **Visualisasi Data** | Chart, graph, heatmap, dan infografis interaktif untuk presentasi ke manajemen. |
| **Custom Report** | Kemampuan membuat laporan visual custom sesuai kebutuhan stakeholder. |
| **Real-time Data** | Data terupdate secara real-time dari semua modul ERP. |

#### 4.2 Trend Analysis
| Komponen | Penjabaran |
|---|---|
| **Tren Penjualan** | Analisis tren penjualan per produk, kategori, area, channel over time. |
| **Tren Pasar** | Monitoring tren pasar dan kompetitor (data eksternal terintegrasi). |
| **Seasonal Pattern** | Identifikasi pola musiman dalam penjualan untuk perencanaan yang lebih baik. |
| **Anomaly Detection** | Deteksi anomali dalam data penjualan yang memerlukan perhatian. |

#### 4.3 Forecast
| Komponen | Penjabaran |
|---|---|
| **Sales Forecast** | Proyeksi penjualan berdasarkan historis, tren, dan input dari tim sales. |
| **Demand Forecast** | Forecast permintaan per SKU/area untuk perencanaan inventory. |
| **Revenue Forecast** | Proyeksi revenue per periode dengan berbagai skenario. |
| **Accuracy Tracking** | Monitoring akurasi forecast vs aktual untuk perbaikan model. |
| **Collaborative Forecast** | Input forecast dari berbagai tim (sales, marketing, supply chain) yang dikonsolidasi. |

---

### 5. 🎛️ Controlling (Commitment & Actual)

| Komponen | Penjabaran |
|---|---|
| **Budget Commitment** | Pencatatan komitmen anggaran: PO, kontrak, persetujuan budget yang belum terealisasi. |
| **Actual Spending** | Pencatatan pengeluaran aktual yang sudah terjadi. |
| **Commitment vs Actual** | Perbandingan real-time antara komitmen dan aktual untuk setiap pos anggaran. |
| **Variance Analysis** | Analisis selisih antara budget, commitment, dan actual dengan penjelasan. |
| **Budget Utilization Rate** | Persentase penggunaan budget per pos/periode. |
| **Alert Over-budget** | Notifikasi otomatis jika spending mendekati atau melebihi budget. |
| **Rolling Forecast** | Update forecast secara berkala berdasarkan actual spending terbaru. |

---

### 6. ✈️ Travel Management (UpCountry, Expense, Akomodasi, Support Dana)

| Komponen | Penjabaran |
|---|---|
| **UpCountry Planning** | Perencanaan perjalanan luar kota: tujuan, durasi, agenda, estimasi biaya. |
| **Expense Management** | Pencatatan dan approval semua pengeluaran perjalanan. |
| **Akomodasi Booking** | Manajemen booking hotel/penginapan terintegrasi dengan travel plan. |
| **Support Dana Perjalanan** | Pengajuan dan pencairan dana support untuk perjalanan (advance, per diem). |
| **Travel Policy Enforcement** | Validasi pengeluaran terhadap kebijakan perjalanan perusahaan (hotel max rate, transport class, dll). |
| **Itinerary Management** | Manajemen itinerary perjalanan dengan integrasi ke kalender. |
| **Travel Report** | Laporan lengkap perjalanan: tujuan, hasil, pengeluaran, ROI perjalanan. |

---

### 7. 📋 Internal Order Report (Commitment vs Actual)

| Komponen | Penjabaran |
|---|---|
| **Internal Order** | Order internal untuk kebutuhan operasional: material, sample, gimmick, event support. |
| **Commitment Tracking** | Tracking komitmen internal order: approved, pending fulfillment. |
| **Actual Fulfillment** | Tracking realisasi pemenuhan internal order. |
| **Variance Report** | Laporan selisih antara commitment dan actual per internal order. |
| **Cost Allocation** | Alokasi biaya internal order ke cost center/project yang relevan. |
| **Approval Workflow** | Workflow persetujuan internal order berdasarkan nilai dan jenis. |

---

### 8. 📣 Trade Promotion Planning and Management

| Komponen | Penjabaran |
|---|---|
| **Promotion Calendar** | Kalender promosi perdagangan: periode, jenis, area, target. |
| **Promotion Types** | Diskon trade, bonus barang, display allowance, listing fee, volume incentive. |
| **Budget Allocation** | Alokasi budget promosi per channel, area, periode. |
| **Promotion Proposal** | Form pengajuan promosi trade dengan justifikasi dan ROI projection. |
| **Approval & Execution** | Workflow persetujuan dan monitoring pelaksanaan promosi. |
| **Effectiveness Analysis** | Analisis efektivitas promosi trade: incremental sales, ROI, lift analysis. |
| **Settlement** | Proses settlement/promotion claim dengan customer/distributor. |

---

### 9. 🎯 Promotion Management

| Komponen | Penjabaran |
|---|---|
| **Campaign Management** | Perencanaan dan eksekusi campaign promosi: brief, timeline, target, budget. |
| **Promotion Mechanics** | Konfigurasi mekanisme promosi: diskon, bundle, GWP (Gift With Purchase), cashback. |
| **Target Audience** | Segmentasi target promosi: pelanggan baru, existing, high-value, dormant. |
| **Multi-channel Promotion** | Promosi melalui berbagai channel: in-store, digital, direct sales, event. |
| **Promotion Tracking** | Monitoring real-time performa promosi: redemption rate, sales lift. |
| **Post-Promotion Analysis** | Evaluasi hasil promosi: achievement vs target, learnings, rekomendasi. |

---

### 10. 🏃 Field Execution & Activity

| Komponen | Penjabaran |
|---|---|
| **Activity Planning** | Perencanaan aktivitas lapangan: merchandising, display, stock check, price check. |
| **Task Assignment** | Penugasan aktivitas ke sales/merchandiser dengan deadline dan checklist. |
| **Execution Tracking** | Monitoring pelaksanaan aktivitas: completed, in-progress, overdue. |
| **Photo Proof** | Upload foto bukti pelaksanaan aktivitas di lapangan. |
| **Compliance Score** | Skor kepatuhan pelaksanaan aktivitas terhadap standar yang ditetapkan. |
| **Field Report** | Laporan aktivitas lapangan dengan insight dan rekomendasi. |
| **GPS Verification** | Verifikasi lokasi pelaksanaan aktivitas via GPS. |

---

### 11. 🎪 Event Planning & Management

| Komponen | Penjabaran |
|---|---|
| **Event Proposal** | Form pengajuan event: konsep, tujuan, target, budget, timeline. |
| **Event Calendar** | Kalender terpusat semua event yang direncanakan dan dilaksanakan. |
| **Resource Planning** | Perencanaan resource untuk event: SDM, material, produk, venue. |
| **Budget Management** | Manajemen anggaran event: planning, commitment, actual, variance. |
| **Execution Checklist** | Checklist pelaksanaan event pra, saat, dan pasca event. |
| **Participant Management** | Registrasi dan tracking peserta event. |
| **Post-Event Report** | Laporan lengkap hasil event: attendance, sales generated, feedback, ROI. |
| **Event Template** | Template event untuk standarisasi perencanaan dan eksekusi. |

---