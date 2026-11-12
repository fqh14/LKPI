# 📝 Rangkuman Catatan Workshop ERP FAT

Berikut adalah rangkuman catatan hasil workshop ERP FAT yang diurutkan berdasarkan urutan halaman gambar:

## 📄 Bagian 1 (Berdasarkan Gambar 1_2.jpeg)
**Topik Utama & Kebutuhan Sistem**
* Laporan ditujukan ke Direktur Keuangan.
* Perlu ada pembagian hak akses seperti di sistem lama.
* Penentuan format laporan.

**1. Finance**
* **Pengajuan Internal:** Alur pengajuan dimulai dari *Draft* -> *ACC* -> Otorisasi terakhir tetap pada atasan (SPV & Manager). Berlaku untuk semua departemen dan diakui sebagai hutang.
* **Pemeriksaan Internal Control:** Akan di-review langsung oleh Direktur Keuangan.
* **Supplier:** Meliputi supplier bahan baku hingga kemasan.
* **Pemeriksaan Pajak:** Perlu pemisahan rekening untuk transaksi PPN dan non-PPN, serta pengelolaan bukti potong.
* **Proses Pengajuan:** Jika pengajuan ditolak, maka harus membuat pengajuan baru. (Jika ditolak -> Batal -> Pengajuan ulang).
* **Sistem Odoo:** *Item group* dan akunnya sudah diakomodir di dalam Odoo. Termasuk untuk proses piutang.
* **Pengajuan Uang & LPDP:** Meliputi *cash advance* & LPDP, perlu ada rekap pengajuan, serta *tracking update* alur pengajuan CV (telah didemokan).
* **Approval Trisa:** Proses approval pengajuan dilakukan secara berjenjang.

**2. TRISA (Operasional & Peti Cash)**
* Membutuhkan rekening koran dan *tracking* pembayaran (terutama jika menggunakan rekening pribadi).
* Alur pembayaran berujung pada pelunasan.
* **Pity Cash (Cabang):** Digunakan untuk operasional, wajib ada Laporan Harian, dicek terlebih dahulu baru dimasukkan ke sistem Zahir. Pengecekan saldo kas dan pengajuan *Top Up* minimal Rp 2 Juta.

---

## 📄 Bagian 2 (Berdasarkan Gambar 2_2.jpeg)
**1. Lanjutan Peti Cash & Pengajuan**
* **Alur Top Up Cabang:** Cabang input *top up* -> alur pengajuan -> ACC -> langsung cek/cash (H/H).
* **Kategori Pengajuan:** Dibagi menjadi PPN, Non-PPN, dan pencairan cek. Dilengkapi dengan jatuh tempo pengajuan, laporan (mingguan/bulanan), dan bisa difilter per departemen.
* **Revisi Pengajuan:** Revisi bisa dilakukan jika sistem belum di-*lock*. Jika sudah di-*lock*, maka harus di-*open* terlebih dahulu.
* **Approval Peti Cash:** Cabang melakukan input, kemudian *approval posting* dilakukan oleh Trisa HO. Jika ada penggunaan dana yang tidak sesuai, bisa ditolak dan harus diganti.
* Pencairan membutuhkan *approval* Direktur Keuangan beserta jadwal pencairan.

**2. Penagihan & Piutang (Account Receivable)**
* **Laporan Aging:** Report piutang *aging* dan *warning aging*.
* **Titip Faktur:** Terdapat form titip faktur, *monitoring*, dan kolom keterangan "faktur sudah kembali" (berhubungan dengan logistik).
* **Modul Penagihan:** Perlu ada *check list* pemberkasan, menu khusus modul penagihan, serta keterangan rekap penagihan (status berkas TT & status pesanan).
* **Laporan Piutang RSM:** Memuat data umur piutang, outlet, invoice, list nominal, hingga total nominal list.

---

## 📄 Bagian 3 (Berdasarkan Gambar 3_2.jpeg)
**1. Piutang TRISA**
* **Rekap Piutang:** Terdiri dari 60 PBF, 90 Rumah Sakit, dan 45 Apotek. Bisa *request Term of Payment* (TOP) -> input TOP di *user* baru.
* **Manajemen Jatuh Tempo:** Tarik data Jatuh Tempo (JT) untuk 1-2 minggu ke depan.
* **Outlet Baru:** Input outlet baru dilakukan di Admin Penjualan.
* **Buka Kunci (Open Lock) Prinsipal:** Membutuhkan approval atasan (Kepala Cabang jika sesuai komitmen, atau Trisa & rekomendasi jika di luar komitmen). 
* **Insentif:** Terdapat *hold* insentif (AR) dan UC.
* **Aturan PO & Plafon:** 
  * 1 PO dibatasi untuk 1 PO.
  * Saran: Limitasi plafon dibuat maksimal 3x dengan skema COD (30 hari / 60 hari).
  * PO harus sesuai dengan surat pesanan.
  * Plafon tidak bisa digunakan untuk maksimal pembelian.
  * Kenaikan limit harus melalui sistem.

**2. Accounting TRISA**
* Input transaksi dilakukan melalui Zahir.
* Pencatatan jurnal pembayaran hutang dan laporan hutang (PT Likuid, Supplier Impor, CV Tiga Likuid).

---

## 📄 Bagian 4 (Berdasarkan Gambar 4_2.jpeg)
**Pajak / TAX**
* **Validasi & Potongan:** Validasi PPN & Non-PPN. Jasa berpotensi dikenakan PPh 23 dan PPh 21 (3 jenis pemotongan pajak).
* **Lain-lain:** Review pajak melibatkan konsultan, pengecekan kurang bayar, dan Lapor SPT.

**Alur PPh 21:**
1. Borang dari divisi HRD.
2. Borang (sudah ada rumus) diisi -> diteruskan ke konsultan.
3. Disetor oleh HRD -> dicatat sebagai pemotongan di slip gaji.
4. Masuk ke laporan pajak -> hitungan e-Finnet -> join.
5. Jika kurang bayar -> buat *billing*.
6. Format perhitungan gaji dapat di-download dalam bentuk XML.

**Ketentuan Tambahan Pajak & Sistem:**
* File excel unduhan disesuaikan dengan jenis pajaknya.
* Status *invoice* harus lengkap untuk mengetahui faktur mana yang bisa dibuatkan faktur pajaknya.
* Pembulatan nominal di-setting 2 desimal.
* **Clone Database:** Diperlukan nomor referensi untuk melakukan *clone database* (terkait jadwal pajak, logistik, konsultan & Pak Tulus).
* **Retur:** Berkas harus lengkap sebelum diretur (meliputi lawan BA, nota retur, dan nota retur pajak).
* **Penerimaan Barang:** Bisa diinput/digunakan meskipun belum terbit *invoice*.
* Nominal pada sistem bisa berubah karena perhitungan Pajak (PPN/PPh/Non-PPN).
* Terdapat proses Rekonsiliasi / Ekualisasi.
* Sistem harus bisa membuat Surat Konfirmasi Piutang secara otomatis.

---

## 📄 Bagian 5 (Berdasarkan Gambar 5_2.jpeg)
**Laporan Tambahan**
* **Filter Laporan:** Terdapat laporan filter sebelum masuk ke *database cloning*, khususnya untuk modul persediaan.
* **Faktur Pajak:** Sistem harus bisa melakukan *attach* faktur pajak dan mengirimkannya langsung ke email.