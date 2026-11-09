# 📚 Dokumentasi & Panduan Modul HRD
**Dokumen Referensi Sistem ERP - PT Likuid Pharmalab Indonesia**

Dokumen ini berisi penjelasan mendetail mengenai fungsi, aturan validasi, dan alur kerja dari 4 modul utama HRD (Jadwal Shift, Cuti & Izin, Lembur, dan Absensi).

---

## 1. 📅 Modul Jadwal Shift (Shiftschedule)
**Deskripsi:** 
Modul ini digunakan untuk menentukan jadwal jam kerja (*shift*) karyawan, baik secara harian maupun dalam rentang tanggal tertentu.

**Fitur & Kolom Utama:**
*   **Karyawan:** Memilih karyawan aktif dari master data.
*   **Tanggal:** Menentukan tanggal efektif *shift*.
*   **Shift:** Memilih kode *shift* (berdasarkan Master Shift).
*   **Keterangan:** Catatan tambahan (opsional).
*   **Import Excel:** Mendukung *upload* jadwal secara massal menggunakan file Excel.

**Aturan & Validasi Sistem:**
1.  **Rentang Tanggal (Excel):** Jika menggunakan fitur *import*, sistem mendukung pengisian `Tanggal Awal` dan `Tanggal Akhir`. Sistem akan secara otomatis memecah (*generate*) jadwal ke setiap hari di antara rentang tanggal tersebut.
2.  **Anti-Duplikasi (Konflik Jadwal):** Karyawan yang sama **tidak boleh** memiliki lebih dari 1 jadwal *shift* pada tanggal yang sama. Sistem akan memblokir dan memberi peringatan jika terdeteksi jadwal ganda (*Double Shift*).
3.  **Validasi Master:** ID Karyawan dan Kode Shift yang diinput (atau di-*import*) harus terdaftar secara valid di Master Karyawan dan Master Shift.
4.  **Draft Mode:** Jadwal dapat disimpan sebagai *Draft* (sementara) sebelum disetujui (*Approve*) menjadi jadwal aktif.

---

## 2. 🏖️ Modul Pengajuan Cuti & Izin (Leavepermit)
**Deskripsi:**
Modul untuk memproses pengajuan cuti tahunan, izin tidak masuk, tukar hari libur (*SWAP*), hingga cuti melahirkan (*Maternity*).

**Fitur & Kolom Utama:**
*   **Karyawan:** Memilih karyawan yang diajukan.
*   **Kategori:** Jenis Cuti/Izin (Contoh: ANNUAL, CUTI, SWAP, MATERNITY).
*   **Tanggal Cuti / Izin:** Tanggal efektif pengajuan (untuk izin/cuti harian).
*   **Tgl Awal & Tgl Akhir:** Khusus untuk tipe rentang hari (seperti *SWAP* dan *Maternity*).

**Aturan & Validasi Sistem:**
1.  **Hierarki & Hak Akses Pengajuan:** 
    *   Pengajuan **tidak bisa** dilakukan langsung oleh level *Staff*.
    *   Hanya **Supervisor (SPV)**, **Manager (MAN)**, atau **Super User (SU / HR)** yang dapat membuat pengajuan.
    *   SPV hanya bisa mengajukan cuti untuk karyawan di **Departemen yang sama** dan maksimal untuk sesama posisi SPV (tidak bisa mengajukan untuk Manager).
2.  **Validasi Hari Libur (Akhir Pekan):** Cuti/Izin harian (reguler) **tidak bisa** diajukan pada hari Sabtu atau Minggu.
3.  **Batas Waktu Pengajuan (Backdate & Future Date):**
    *   **Cuti Tahunan (ANNUAL/CUTI) & Maternity:** Minimal harus diajukan **H-7** (7 hari sebelum keberangkatan). Tidak boleh *backdate* (menggunakan tanggal yang sudah lewat).
    *   **Tukar Hari (SWAP):** Tidak boleh *backdate*. Harus hari ini atau hari kedepan.
    *   **Izin Biasa:** Batas maksimal *backdate* adalah **H-7** (maksimal mundur 7 hari ke belakang).
4.  **Blokir Perubahan Lintas Hari:** Jika dokumen sudah diajukan di hari sebelumnya, *user* tidak diperkenankan menambah baris karyawan baru atau rincian baru ke dalam dokumen tersebut.
5.  **Konflik Cuti:** Sistem menolak pengajuan jika karyawan sudah memiliki cuti/izin aktif di tanggal tersebut.

---

## 3. ⏱️ Modul Pengajuan Lembur (Overtime)
**Deskripsi:**
Modul untuk mencatat dan menghitung pengajuan jam kerja tambahan (lembur) karyawan di luar jam *shift* normal.

**Fitur & Kolom Utama:**
*   **Karyawan:** Memilih karyawan yang akan lembur.
*   **Tanggal:** Tanggal pelaksanaan lembur.
*   **Jam Mulai & Jam Selesai:** Waktu spesifik lembur dilakukan.
*   **Jumlah Jam (Otomatis):** Sistem menghitung durasi lembur secara otomatis berdasarkan Jam Mulai dan Jam Selesai.

**Aturan & Validasi Sistem:**
1.  **Pembulatan Waktu Otomatis (30 Menit):** Durasi lembur otomatis dihitung dan dibulatkan ke kelipatan **30 menit terdekat**. (Contoh: lembur 1 jam 10 menit akan dihitung 1 jam; lembur 1 jam 20 menit akan dibulatkan ke 1.5 jam).
2.  **Hak Akses:** Sama seperti Cuti, lembur hanya diajukan oleh SPV/MAN/SU untuk departemennya sendiri. SPV tidak bisa mengajukan lembur untuk Manager.
3.  **Batas Waktu Klaim:** 
    *   Lembur yang sudah lewat (Klaim) maksimal bisa diinput **H-2**.
    *   Lembur terencana (Ke depan) maksimal bisa diinput **H+7**.
4.  **Validasi Lintas Malam (*Cross-Midnight*):** Jika Jam Selesai lebih kecil dari Jam Mulai (misal: Mulai 22:00, Selesai 02:00), sistem otomatis mendeteksinya sebagai hari berikutnya.
5.  **Anti-Irisan Jam Kerja (Overlap Check):** Jam lembur **DITOLAK** jika beririsan (berada di dalam) jam kerja *shift* normal karyawan tersebut. Lembur murni harus di luar jam kerja.
6.  **Validasi Berlapis:**
    *   Tidak bisa lembur jika karyawan sedang terdaftar **Cuti/Izin** di hari tersebut.
    *   Karyawan tidak boleh diinput lembur 2 kali di tanggal yang sama (digabungkan dalam 1 baris waktu).

---

## 4. 📇 Modul Absensi (Attendance / Fingerspot)
**Deskripsi:**
Modul ini bertugas sebagai penampung data kehadiran aktual karyawan hasil tarikan mesin sidik jari (*Fingerspot*), yang berfungsi sebagai dasar *payroll*.

**Fitur & Kolom Utama:**
*   **Karyawan:** Referensi karyawan (berdasarkan `attendancecode` / ID Mesin).
*   **Lokasi (Mesin):** Lokasi mesin tempat absen dilakukan.
*   **Tanggal, Jam Masuk, Jam Keluar:** Waktu aktual kehadiran.
*   **Import Excel Khusus:** Dirancang khusus untuk membaca format *raw data* dari mesin sidik jari/Fingerspot.

**Aturan & Validasi Sistem:**
1.  **Pencocokan Cerdas (Smart Mapping):** 
    *   Sistem mencari Karyawan berdasarkan kombinasi **ID Mesin (ID Karyawan)** dan **Lokasi Mesin**.
    *   Secara otomatis membersihkan sel Excel dari *enter/newline* ganda (karakter berbaris di Excel).
2.  **Penanganan Absensi Lintas Malam (Cross-Midnight):**
    *   Jika *shift* masuk malam dan pulang pagi (contoh: Jam Keluar lebih kecil dari Jam Masuk), sistem akan menyimpan "Jam Keluar" tersebut ke catatan absensi hari **sebelumnya** (*carry-over*).
3.  **Deteksi Shift Otomatis (Jika Rolling Kosong):** 
    *   Jika karyawan memiliki jadwal terdaftar (*Rolling*), sistem akan mencocokkan jam masuk/keluar dengan shiftnya.
    *   Jika tidak ada jadwal yang pas, sistem mencari *Shift* aktif terdekat berdasarkan jam kedatangan karyawan untuk menyesuaikan batas waktu absen.
4.  **Blokir Absensi saat Cuti:** Jika pada hari tersebut karyawan berstatus Cuti/Izin di modul *Leavepermit*, data absen yang ditarik dari mesin **akan diabaikan** oleh sistem (dianggap tidak valid/ditolak).
5.  **Kewajiban Jadwal (*Strict Rolling*):** Untuk karyawan tipe *ROLLING*, wajib memiliki Jadwal Shift (Modul 1) di tanggal tersebut agar datanya bisa ditarik. Jika tidak ada jadwal, sistem menolak tarikan absen.