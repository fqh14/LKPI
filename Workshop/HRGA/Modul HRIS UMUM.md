# 📚 Dokumentasi & Panduan Modul HRD

**Dokumen Referensi Sistem ERP&nbsp;**

Dokumen ini berisi penjelasan mendetail mengenai fungsi, aturan validasi, dan alur kerja dari 4 modul utama HRD (Jadwal Shift, Cuti & Izin, Lembur, dan Absensi).

---

## 1\. 📅 **Modul Jadwal Shift (Shiftschedule)**

**Deskripsi:** Modul ini digunakan untuk menentukan jadwal jam kerja (*shift*) karyawan, baik secara harian maupun dalam rentang tanggal tertentu.

**Fitur & Kolom Utama:**

* **Karyawan:** Memilih karyawan aktif dari master data.  
* **Tanggal:** Menentukan tanggal efektif *shift*.  
* **Shift:** Memilih kode *shift* (berdasarkan Master Shift).  
* **Keterangan:** Catatan tambahan (opsional).  
* **Import Excel:** Mendukung *upload* jadwal secara massal menggunakan file Excel.

Shift **Produksi** :&nbsp;

* Shift 1   \= 07.00 \- 16.00  
* Shift 2   \= 15.00 \- 24.00  
* Shift 3 	\= 23.00 \- 08.00

Shift **Satpam** :&nbsp;

* Shift 1 \= 07.00 \- 15.00  
* Shift 2 \= 15.00 \- 23.00  
* Shift 3 \= 23.00 \- 07.00

Shift **CS** :

* Baki  	\= 06.00 \- 14.00 	\| 07.00 \- 15.00 		\| 08.00 \- 16.00  
* Kleco 	\= 07.00 \- 15.00,  	Sabtu \= 07.00 \- 12.00  
* Bladon \= 06.00 \- 14.00, 	Sabtu \=07.00 \- 12.00&nbsp;  
* Trisa     \= 07.00 \- 15.00, 	Sabtu \= 07.00 \- 12.00

**Aturan & Validasi Sistem:**

1. **Rentang Tanggal (Excel):** Jika menggunakan fitur *import*, sistem mendukung pengisian `Tanggal Awal` dan `Tanggal Akhir`. Sistem akan secara otomatis memecah (*generate*) jadwal ke setiap hari di antara rentang tanggal tersebut.  
2. **Anti-Duplikasi (Konflik Jadwal):** Karyawan yang sama **tidak boleh** memiliki lebih dari 1 jadwal *shift* pada tanggal yang sama. Sistem akan memblokir dan memberi peringatan jika terdeteksi jadwal ganda (*Double Shift*).  
3. **Validasi Master:** ID Karyawan dan Kode Shift yang diinput (atau di-*import*) harus terdaftar secara valid di Master Karyawan dan Master Shift.  
4. **Draft Mode:** Jadwal dapat disimpan sebagai *Draft* (sementara) sebelum disetujui (*Approve*) menjadi jadwal aktif.

---

## 2\. 🏖️ **Modul Pengajuan Cuti & Izin (Leavepermit)**

**Deskripsi:** Modul untuk memproses pengajuan cuti tahunan, izin tidak masuk, tukar hari libur (*SWAP*), hingga cuti melahirkan (*Maternity*).

**Fitur & Kolom Utama:**

* **Karyawan:** Memilih karyawan yang diajukan.  
* **Kategori:** Jenis Cuti/Izin (Contoh: ANNUAL, CUTI, SWAP, MATERNITY).  
* **Tanggal Cuti / Izin:** Tanggal efektif pengajuan (untuk izin/cuti harian).  
* **Tgl Awal & Tgl Akhir:** Khusus untuk tipe rentang hari (seperti *SWAP* dan *Maternity*).

**Aturan & Validasi Sistem:**

1. **Hierarki & Hak Akses Pengajuan:**  
   * Pengajuan **tidak bisa** dilakukan langsung oleh level *Staff*.  
   * Hanya **Supervisor (SPV)**, **Manager (MAN)**, atau **Super User (SU / HR)** yang dapat membuat pengajuan.  
   * SPV hanya bisa mengajukan cuti untuk karyawan di **Departemen yang sama** dan maksimal untuk sesama posisi SPV (tidak bisa mengajukan untuk Manager).  
2. **Validasi Hari Libur (Akhir Pekan):** Cuti/Izin harian (reguler) **tidak bisa** diajukan pada hari Sabtu atau Minggu.  
3. **Batas Waktu Pengajuan (Backdate & Future Date):**  
   * **Cuti Tahunan (ANNUAL/CUTI) & Maternity:** Minimal harus diajukan **H-7** (7 hari sebelum keberangkatan). Tidak boleh *backdate* (menggunakan tanggal yang sudah lewat).  
   * **Tukar Hari (SWAP):** Tidak boleh *backdate*. Harus hari ini atau hari kedepan.  
   * **Izin Biasa:** Batas maksimal *backdate* adalah **H** (hari tersebut).  
4. **Blokir Perubahan Lintas Hari:** Jika dokumen sudah diajukan di hari sebelumnya, *user* tidak diperkenankan menambah baris karyawan baru atau rincian baru ke dalam dokumen tersebut.  
5. **Konflik Cuti:** Sistem menolak pengajuan jika karyawan sudah memiliki cuti/izin aktif di tanggal tersebut.

---

## 3\. ⏱️ **Modul Pengajuan Lembur (Overtime)**

**Deskripsi:** Modul untuk mencatat dan menghitung pengajuan jam kerja tambahan (lembur) karyawan di luar jam *shift* normal.

**Fitur & Kolom Utama:**

* **Karyawan:** Memilih karyawan yang akan lembur.  
* **Tanggal:** Tanggal pelaksanaan lembur.  
* **Jam Mulai & Jam Selesai:** Waktu spesifik lembur dilakukan.  
* **Jumlah Jam (Otomatis):** Sistem menghitung durasi lembur secara otomatis berdasarkan Jam Mulai dan Jam Selesai.

**Aturan & Validasi Sistem:**

1. **Pembulatan Waktu Otomatis (30 Menit):** Durasi lembur otomatis dihitung dan dibulatkan ke kelipatan **30 menit terdekat**. (Contoh: lembur 1 jam 10 menit akan dihitung 1 jam; lembur 1 jam 20 menit akan dibulatkan ke 1.5 jam).  
2. **Hak Akses:** Sama seperti Cuti, lembur hanya diajukan oleh SPV/MAN/SU untuk departemennya sendiri. SPV tidak bisa mengajukan lembur untuk Manager.  
3. **Batas Waktu Klaim:**  
   * Lembur yang sudah lewat (Klaim) maksimal bisa diinput **H+2**.  
   * Lembur terencana (Ke depan) maksimal bisa diinput **H-2**.  
4. **Validasi Lintas Malam (*Cross-Midnight*):** Jika Jam Selesai lebih kecil dari Jam Mulai (misal: Mulai 22:00, Selesai 02:00), sistem otomatis mendeteksinya sebagai hari berikutnya.  
5. **Anti-Irisan Jam Kerja (Overlap Check):** Jam lembur **DITOLAK** jika beririsan (berada di dalam) jam kerja *shift* normal karyawan tersebut. Lembur murni harus di luar jam kerja.  
6. **Validasi Berlapis:**  
   * Tidak bisa lembur jika karyawan sedang terdaftar **Cuti/Izin** di hari tersebut.  
   * Karyawan tidak boleh diinput lembur 2 kali di tanggal yang sama (digabungkan dalam 1 baris waktu).

---

## 4\. 📇 **Modul Absensi (Attendance / Fingerspot)**

**Deskripsi:** Modul ini bertugas sebagai penampung data kehadiran aktual karyawan hasil tarikan mesin sidik jari (*Fingerspot*), yang berfungsi sebagai dasar *payroll*.

**Fitur & Kolom Utama:**

* **Karyawan:** Referensi karyawan (berdasarkan `attendancecode` / ID Mesin / force field ).  
* **Lokasi (Mesin):** Lokasi mesin tempat absen dilakukan.  
* **Tanggal, Jam Masuk, Jam Keluar:** Waktu aktual kehadiran.  
* **Import Excel Khusus:** Dirancang khusus untuk membaca format *raw data* dari mesin sidik jari/Fingerspot.

**Aturan & Validasi Sistem:**

1. **Pencocokan Cerdas (Smart Mapping):**  
   * Sistem mencari Karyawan berdasarkan kombinasi **ID Mesin (ID Karyawan)** dan **Lokasi Mesin**.  
   * Secara otomatis membersihkan sel Excel dari *enter/newline* ganda (karakter berbaris di Excel).  
2. **Penanganan Absensi Lintas Malam (Cross-Midnight):**  
   * Jika *shift* masuk malam dan pulang pagi (contoh: Jam Keluar lebih kecil dari Jam Masuk), sistem akan menyimpan "Jam Keluar" tersebut ke catatan absensi hari **sebelumnya** (*carry-over*).  
3. **Deteksi Shift Otomatis (Jika Rolling Kosong):**  
   * Jika karyawan memiliki jadwal terdaftar (*Rolling*), sistem akan mencocokkan jam masuk/keluar dengan shiftnya.  
   * Jika tidak ada jadwal yang pas, sistem mencari *Shift* aktif terdekat berdasarkan jam kedatangan karyawan untuk menyesuaikan batas waktu absen.  
4. **Blokir Absensi saat Cuti:** Jika pada hari tersebut karyawan berstatus Cuti/Izin di modul *Leavepermit*, data absen yang ditarik dari mesin **akan diabaikan** oleh sistem (dianggap tidak valid/ditolak).  
5. **Kewajiban Jadwal (*Strict Rolling*):** Untuk karyawan tipe *ROLLING*, wajib memiliki Jadwal Shift (Modul 1\) di tanggal tersebut agar datanya bisa ditarik. Jika tidak ada jadwal, sistem menolak tarikan absen.

---

## **5\. 📱 Modul Absensi Sales Force Field / Mobile (PWA)**

**Deskripsi:** Berdasarkan ruang lingkup spesifik dalam Surat Perjanjian Kerjasama (SPK) pada modul CRM & Sales (`SH-SALES/MS` untuk PT LKPI dan `TRI-MS` untuk PT Trisa Likuid Farma), disediakan fitur khusus absensi lapangan bagi tenaga pemasaran (*Sales Force*) berbasis aplikasi *Progressive Web App* (PWA).

**Fitur & Mekanisme Utama:**

* **Presensi Berbasis Mobile (PWA):** Memungkinkan *sales* dan tim marketing melakukan absensi langsung dari perangkat *smartphone* tanpa harus datang ke kantor pusat atau cabang.  
* **Validasi Geo-tagging (Tag Lokasi):** Sistem mewajibkan penyertaan koordinat GPS real-time saat melakukan absensi untuk memastikan posisi *sales* berada di area outlet, rumah sakit, apotek, atau titik kunjungan (*up country*) yang sah.  
* **Bukti Foto di Outlet (*Photo Evidence*):** Sesuai spesifikasi MoU/SPK, proses absensi marketing wajib disertai dengan pengambilan foto verifikasi bersama di outlet tujuan.  
* **Integrasi Kunjungan (*Visit Plan & Realization*):** Catatan absensi lapangan terhubung langsung dengan jadwal kunjungan (*visit plan*) harian, pengajuan akomodasi, *expense*, hingga *Support Dana/DPF*.

---

## 6\. **📊 Modul Laporan Kehadiran (Reportattendance)**

**Deskripsi:** Modul laporan komprehensif yang menampilkan rekapitulasi kehadiran karyawan secara rinci dalam bentuk kalender penuh (Full Calendar Data Engine). Modul ini menggabungkan data absensi aktual, status cuti/izin, hari libur akhir pekan, hingga deteksi ketidakhadiran (mangkir).

Fitur & Filter Utama:

* **Periode Tanggal (Start Date & End Date):** Menentukan rentang waktu laporan yang ingin dicetak atau ditampilkan.  
* **Filter Karyawan:** Memilih karyawan tertentu (berdasarkan format Kode \- Nama Karyawan) atau menampilkan seluruh karyawan.  
* **Filter Mesin Finger:** Menyaring data berdasarkan lokasi mesin absensi tertentu.  
* **Ekspor Dokumen:** Menyediakan fitur unduh laporan ke format **Excel (.xlsx)** dan **PDF Landscape (A4)** secara instan.

**Mekanisme Inti & Kecerdasan Sistem (GenerateFullCalendarData):** Tidak seperti laporan database konvensional, modul ini secara otomatis merekonstruksi catatan harian karyawan dari rentang tanggal yang dipilih melalui 4 lapisan prioritas:

1. **Data Kehadiran Aktual:** Jika karyawan melakukan fingerprint pada tanggal tersebut, sistem menampilkan jam masuk (time in), jam keluar (time out), lokasi mesin, serta nomor dokumen absensinya.  
2. **Integrasi Cuti & Izin:** Jika pada tanggal tersebut tidak ada data absen tetapi karyawan memiliki catatan cuti/izin yang telah disetujui (Leavepermit), sistem secara otomatis mengisi kolom keterangan dengan jenis cutinya (misal: ANNUAL (CUTI TAHUNAN), MATERNITY, dll).  
3. **Deteksi Hari Libur Akhir Pekan:** Jika tanggal tersebut jatuh pada hari Sabtu atau Minggu dan tidak ada aktivitas absensi/cuti, sistem otomatis memberikan keterangan **"Libur Akhir Pekan"**.  
4. **Deteksi Ketidakhadiran (Mangkir):** Jika hari kerja biasa (Senin s.d. Jumat) dilalui tanpa ada data absen dan tanpa cuti yang disetujui, sistem menandainya sebagai **"Tidak Hadir"**.

Hal ini memastikan laporan kehadiran yang dihasilkan bersih, transparan, dan siap diverifikasi oleh manajemen atau bagian HRD untuk keperluan payroll.

## 

## 