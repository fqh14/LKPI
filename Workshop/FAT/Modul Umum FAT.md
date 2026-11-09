# Dokumentasi Modul Finance, Accounting, & Tax (FAT)

**Sistem ERP Enterprise Terintegrasi**

Dokumen ini memuat arsitektur, alur data, dan penjabaran teknis dari modul *Finance, Accounting, dan Tax* berdasarkan *Controller* dan *Model* yang ada di dalam sistem.

## 1. Mesin Utama Akuntansi (Core Engine): `MJournal`

`MJournal` adalah sentral / pusat dari seluruh pencatatan *Double-Entry Bookkeeping* di dalam sistem. Alih-alih menulis logika jurnal di setiap *controller* transaksi, sistem memusatkannya di model ini.

### a. Triggers & Source (Sumber Jurnal)

Metode `createJournal` dan `updateJournal` menerima parameter `source` untuk membedakan perlakuan debet/kredit. Sumber yang didukung meliputi 10 siklus logistik dan finansial:

 1. **GRPO (Goods Receipt PO):** Pencatatan persediaan masuk vs Hutang Barang Belum Ditagih (*Goods Clearing*).
 2. **APINV (A/P Invoice):** Pengakuan Hutang Dagang (AP) dari *Supplier*.
 3. **DO (Delivery Order):** Pemotongan persediaan dan pengakuan Harga Pokok Penjualan (HPP/COGS).
 4. **ARINV (A/R Invoice):** Pengakuan Piutang Usaha (AR) dan Pendapatan Penjualan (*Revenue*).
 5. **OUTPAY (Outgoing Payment):** Pembayaran kas/bank keluar untuk melunasi Hutang (AP).
 6. **INPAY (Incoming Payment):** Penerimaan kas/bank masuk dari pelunasan Piutang (AR).
 7. **IP (Issue for Production):** Pengeluaran bahan baku (WIP/Beban Produksi vs Persediaan).
 8. **RP (Receipt from Production):** Penerimaan barang jadi (Persediaan vs Clearing Produksi).
 9. **LOADING (Loading & Ekspedisi):** Pemindahan nilai inventori gudang *Staging* ke status *Shipped Goods*.
10. **STOCK_OPNAME:** Penyesuaian nilai persediaan terhadap *Gain/Loss* opname fisik.

### b. Dynamic COA Mapping (Pemetaan Akun Dinamis)

`MJournal` tidak melakukan *hard-code* pada nomor akun. Sistem membaca relasi secara dinamis:
* **Transaksi -> Item Code -> Item Master -> Item Group -> Akun COA**

### c. Auto-Reversal & Update Jurnal

Jika terjadi pembatalan (`savecancel`) atau revisi (`saveedit`) pada dokumen referensi:
* Sistem memanggil `updateStatusByReference($txnNumber, 0)` atau `deleteByReference($txnNumber)` untuk membatalkan jurnal lama, menjaga agar neraca saldo (*trial balance*) tetap akurat tanpa adanya *orphan data*.

## 2. Manajemen Pajak (Tax Management)

Logika perhitungan pajak disematkan secara mulus di berbagai modul transaksi (SO, PO, SQ) yang berkomunikasi dengan modul **MTax**.

### a. Variabel Kalkulasi (Subtotal, DPP, & Tax)
Pada setiap `Controller` transaksi, sistem memanggil `calculateHeaderTotals()` didasari parameter `taxclude`:
* `taxclude = 0` (Exclude): Pajak dihitung **di luar** subtotal. (DPP = Subtotal - Diskon).
* `taxclude = 1` (Include): Pajak **sudah termasuk** di dalam harga barang. (DPP = Grand Total / (1 + Rate Pajak)).

### b. Jurnal Pajak (VAT / PPN)
* **Pajak Masukan (VAT In):** Dieksekusi saat GRPO atau *A/P Invoice*.
* **Pajak Keluaran (VAT Out):** Dieksekusi saat *A/R Invoice*.

## 3. Account Payable & Receivable (Hutang & Piutang)

Sistem menggunakan siklus pembayaran terstruktur menggunakan modul berbasis `BasePaymentController`.

### a. `Outgoingpayment` (Pembayaran Keluar)
* **Relasi:** Memotong saldo dokumen `MApinvoice` (Hutang Vendor).
* **Penjurnalan:** Debit Akun A/P, Kredit Kas/Bank, Kredit Diskon Pembelian (jika ada).

### b. `Incomingpayment` (Penerimaan Pembayaran)
* **Relasi:** Memotong saldo tagihan dari *Customer* di modul *AR Invoice*.
* **Penjurnalan:** Debit Kas/Bank, Debit Diskon Penjualan (jika ada), Kredit Akun A/R.

## 4. Perlindungan Margin & Valuasi Multi-Mata Uang

* **Pricelist & Discountlist (*Revenue Control*):** Harga jual divalidasi ke master `Pricelist` yang aktif (FEFO / *validity date*). Diskon baris divalidasi batas atasnya melawan `Discountlist`.
* **MCurrency & Exchangerate:** Mengelola nilai tukar transaksi mata uang asing dengan sistem *anti-collision*.

## 5. Costing & Valuasi Produksi (WIP & COGS)

* **Pemakaian Bahan (IP):** Debit akun WIP, Kredit akun Inventori Bahan Baku berdasarkan unit cost.
* **Penerimaan Produksi (RP):** Debit akun Inventori Barang Jadi, Kredit akun Clearing WIP.
* **HPP Penjualan (DO / Loading):** Debit akun HPP (COGS), Kredit inventori/stok dalam perjalanan.

---

## 6. Pemetaan 26 Akun (COA Mapping) pada Item Group

Untuk mendukung otomatisasi *MJournal*, sistem menggunakan 26 parameter akun pada level **Item Group** (Grup Barang). Hal ini memungkinkan pemisahan pencatatan antara bahan baku, barang setengah jadi, barang jadi, atau jasa secara otomatis. Berikut adalah penjelasan detail dari masing-masing fungsi akun tersebut:

### A. Siklus Penjualan (Sales & Revenue)
1. **REVENUE ACCOUNT (Akun Pendapatan):** Dikredit saat *A/R Invoice* diterbitkan. Ini adalah akun pengakuan pendapatan utama perusahaan atas penjualan barang terkait.
2. **COST OF GOODS SOLD ACCOUNT (Akun HPP):** Didebet saat *Delivery Order* (DO) atau saat barang terjual, yang mewakili Harga Pokok Penjualan dari inventori yang keluar.
3. **SALES RETURN ACCOUNT (Akun Retur Penjualan):** Didebet ketika pelanggan mengembalikan barang (A/R Credit Memo), mengurangi total pendapatan kotor.
4. **SALES CREDIT ACCOUNT (Akun Kredit/Potongan Penjualan):** Digunakan untuk mencatat potongan harga, diskon khusus, atau *credit memo* penjualan selain dari retur fisik.
5. **SHIPPED GOODS ACCOUNT (Akun Barang Terkirim):** Akun transit persediaan. Didebet ketika barang sudah dikirim secara fisik ke pelanggan (Delivery) namun belum diterbitkan *A/R Invoice*-nya.
6. **VAT OUT ACCOUNT (Akun PPN Keluaran):** Dikredit saat *A/R Invoice* yang dikenakan Pajak Pertambahan Nilai diterbitkan kepada pelanggan.

### B. Siklus Pembelian & Persediaan (Purchasing & Inventory)
7. **INVENTORY ACCOUNT (Akun Persediaan):** Akun riil (Neraca) yang menyimpan nilai aset barang di gudang. Didebet saat barang masuk (GRPO), dan dikredit saat barang keluar (DO/Issue).
8. **EXPENSE ACCOUNT (Akun Beban):** Digunakan sebagai akun beban langsung jika item tersebut dikategorikan sebagai *Non-Inventory Item* (misalnya pembelian alat tulis atau jasa pihak ketiga melalui siklus PO).
9. **GOODS CLEARING ACCOUNT (Akun Hutang Belum Ditagih/GRNI):** Akun penyeimbang (kewajiban sementara) yang dikredit saat GRPO (penerimaan barang). Akan dibalik (didebet) ketika tagihan asli atau *A/P Invoice* diterima dari *Supplier*.
10. **PURCHASE CREDIT ACCOUNT (Akun Kredit Pembelian):** Digunakan untuk mencatat potongan pembelian atau memo kredit dari vendor.
11. **PRICE DIFFERENCE ACCOUNT (Akun Selisih Harga):** Menampung selisih nilai jika harga saat *A/P Invoice* berbeda dengan harga saat *Goods Receipt PO* (GRPO).
12. **VAT IN ACCOUNT (Akun PPN Masukan):** Didebet saat menerima tagihan (*A/P Invoice*) atau GRPO yang mengandung Pajak Pertambahan Nilai dari *Supplier*.

### C. Penyesuaian & Transit Logistik (Adjustment & Transfer)
13. **STOCK IN TRANSIT ACCOUNT (Akun Stok Dalam Perjalanan):** Menampung nilai barang yang sedang dipindahkan antar gudang (Inter-Warehouse Transfer) atau sedang dalam proses staging/loading ekspedisi.
14. **G/L INCREASE ACCOUNT (Akun Kenaikan G/L):** Akun pendapatan/keuntungan (*Gain*) yang dikredit saat hasil *Stock Opname* menunjukkan kuantitas fisik **lebih banyak** daripada sistem.
15. **G/L DECREASE ACCOUNT (Akun Penurunan G/L):** Akun beban/kerugian (*Loss*) yang didebet saat hasil *Stock Opname* menunjukkan kuantitas fisik **lebih sedikit** (hilang/rusak) daripada sistem.
16. **INVENTORY OFFSET INCREASE ACCOUNT:** Akun perantara/lawan saat persediaan bertambah melalui dokumen penerimaan barang non-pembelian (*Goods Receipt* / *Inventory In*).
17. **INVENTORY OFFSET DECREASE ACCOUNT:** Akun perantara/lawan saat persediaan berkurang melalui dokumen pengeluaran barang non-penjualan (*Goods Issue* / *Inventory Out*).
18. **NEGATIVE INVENTORY ACCOUNT:** Akun penyesuaian khusus jika sistem memperbolehkan stok minus (berguna untuk valuasi koreksi akibat transaksi yang mendahului penerimaan).
19. **INVENTORY OFFSET ACCOUNT:** Akun penyeimbang general yang sering digunakan saat pengisian saldo awal persediaan (*Opening Balance*).

### D. Siklus Pabrikasi / Produksi (Manufacturing)
20. **WIP INVENTORY ACCOUNT (Akun Barang Dalam Proses):** Didebet ketika bahan baku dikeluarkan dari gudang untuk masuk ke lini produksi (*Issue for Production*). Menggambarkan nilai aset yang sedang diproses.
21. **WIP OFFSET ACCOUNT (Akun Penyeimbang WIP):** Akun lawan (kredit) yang digunakan saat material dilempar ke produksi, biasanya berfungsi sebagai *clearing* akun sebelum menjadi barang jadi.
22. **WIP INVENTORY VARIANCE ACCOUNT (Akun Selisih WIP):** Mencatat selisih (*variance*) antara biaya material standar (Standard Cost) dengan biaya aktual pada saat *Work Order* (WO) ditutup (*Closing/Receipt from Production*).

### E. Akun Sistem & Alokasi Tambahan
23. **EXCHANGE RATE ACCOUNT (Akun Selisih Kurs):** Menampung nilai untung/rugi (*realized/unrealized gain & loss*) akibat perbedaan fluktuasi nilai tukar mata uang antara dokumen PO/AR dengan saat pembayaran (Payment).
24. **ALLOCATION ACCOUNT (Akun Alokasi):** Akun perantara sementara untuk menampung biaya-biaya (*landed costs* seperti ongkir, bea cukai) yang belum teralokasi sempurna ke nilai HPP atau persediaan barang.
25. **VARIANCE ACCOUNT (Akun Selisih Valuasi):** Akun umum yang menampung selisih-selisih pembulatan atau selisih sistem akibat metode valuasi rata-rata bergerak (*Moving Average*) atau standard.
26. **EXPENSE CLEAR ACCOUNT (Akun Clearing Beban):** Digunakan untuk menjurnal *Non-Inventory Item* yang mana barang/jasanya sudah diterima namun tagihannya belum dibukukan (seperti *Goods Clearing*, namun khusus untuk beban operasional).