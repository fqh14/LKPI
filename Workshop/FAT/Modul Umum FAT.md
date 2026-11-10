# 📚 Dokumentasi & Panduan Operasional Modul Finance, Accounting, & Tax (FAT)

**Sistem ERP Enterprise Terintegrasi**

Dokumen ini memuat arsitektur menyeluruh, alur data, penjabaran teknis, serta panduan operasional dari modul *Finance, Accounting, and Tax* (FAT) berdasarkan integrasi *Controller* dan *Model* di dalam sistem ERP. Dokumen ini dirancang untuk memandu tim FAT dalam memahami otomatisasi penjurnalan, titik kendali (*control points*), manajemen pajak, serta pemetaan akun buku besar.

---

## ⚙️ 1. Arsitektur Mesin Akuntansi Otomatis (`MJournal`)

`MJournal` adalah pusat (*core engine*) dari seluruh pencatatan *Double-Entry Bookkeeping* di dalam sistem. Alih-alih menulis logika jurnal di setiap *controller* transaksi, sistem memusatkannya di model ini secara *Subledger-to-General Ledger Automation*.

### 🔄 a. 10 Sumber Transaksi Akuntansi Utama (`source`)

Metode `createJournal` dan `updateJournal` menerima parameter `source` untuk membedakan perlakuan debet/kredit secara otomatis pada 10 siklus operasional berikut:

1. **GRPO (*Goods Receipt PO*):** Pencatatan persediaan masuk vs Hutang Barang Belum Ditagih (*Goods Clearing*).
2. **APINV (*A/P Invoice*):** Pengakuan Hutang Dagang resmi dari *Supplier* sekaligus melikuidasi akun *Goods Clearing*.
3. **DO (*Delivery Order*):** Mengeluarkan fisik barang dari inventori dan mengakui Harga Pokok Penjualan (HPP / COGS).
4. **ARINV (*A/R Invoice*):** Pengakuan Piutang Usaha (AR) dan Pendapatan Penjualan (*Revenue*).
5. **OUTPAY (*Outgoing Payment*):** Pencatatan pengeluaran kas/bank untuk melunasi Hutang Dagang (`MApinvoice`).
6. **INPAY (*Incoming Payment*):** Pencatatan penerimaan kas/bank dari pelunasan Piutang Usaha (`MArinvoice`).
7. **IP (*Issue for Production*):** Mengeluarkan bahan baku ke lini produksi (WIP vs Persediaan Bahan Baku).
8. **RP (*Receipt from Production*):** Menerima barang jadi dari pabrik ke gudang (Persediaan Barang Jadi vs WIP Clearing).
9. **LOADING (*Loading & Ekspedisi*):** Memindahkan nilai inventori dari gudang *Staging* (*Packing*) ke status barang terkirim (*Shipped Goods*).
10. **STOCK_OPNAME:** Menyesuaikan nilai persediaan fisik terhadap selisih kurang/lebih (*Gain/Loss*).

### 🗺️ b. Mekanisme Pemetaan Akun Dinamis (*Dynamic COA Mapping*)

`MJournal` membaca hierarki akun secara dinamis tanpa *hard-code*, melalui alur relasi:

$$\text{Transaksi Detail} \rightarrow \text{Item Master} \rightarrow \text{Item Group} \rightarrow \text{Parameter Akun (26 COA)}$$

Hal ini memastikan setiap kategori produk (Bahan Baku, Barang Setengah Jadi, Barang Jadi, Jasa) masuk ke pos neraca atau laba rugi yang tepat secara otomatis.

### ↩️ c. Auto-Reversal & Update Jurnal

Jika terjadi pembatalan (`savecancel`) atau revisi (`saveedit`) pada dokumen referensi:
* Sistem otomatis memanggil `updateStatusByReference($txnNumber, 0)` atau `deleteByReference($txnNumber)` untuk membalikkan/menghapus jurnal lama, menjaga neraca saldo (*trial balance*) tetap akurat tanpa data yatim (*orphan data*).

---

## 🧾 2. Manajemen Pajak (Tax Management)

Logika perhitungan pajak disematkan secara mulus di berbagai modul transaksi (SO, PO, SQ) yang berkomunikasi dengan modul **MTax**.

### 🧮 a. Variabel Kalkulasi (Subtotal, DPP, & Tax)

Pada setiap *Controller* transaksi, sistem mengeksekusi `calculateHeaderTotals()` berdasarkan parameter `taxclude`:
* `taxclude = 0` (Exclude): Pajak dihitung **di luar** subtotal. ($\text{DPP} = \text{Subtotal} - \text{Diskon}$).
* `taxclude = 1` (Include): Pajak **sudah termasuk** di dalam harga barang. ($\text{DPP} = \text{Grand Total} / (1 + \text{Rate Pajak})$).

### 📑 b. Jurnal Pajak (VAT / PPN)

* **Pajak Masukan (VAT In):** Dieksekusi otomatis saat GRPO atau *A/P Invoice*.
* **Pajak Keluaran (VAT Out):** Dieksekusi otomatis saat *A/R Invoice*.

---

## 💳 3. Account Payable & Receivable (Hutang & Piutang)

Sistem menggunakan siklus pembayaran terstruktur melalui modul berbasis `BasePaymentController`.

### 📤 a. `Outgoingpayment` (Pembayaran Keluar / Hutang)

* **Relasi:** Memotong saldo dokumen `MApinvoice` (Hutang Vendor) berdasarkan sisa tagihan (*Balance Due*).
* **Penjurnalan:** Debit Akun A/P, Kredit Kas/Bank, Kredit Diskon Pembelian (jika ada).

### 📥 b. `Incomingpayment` (Penerimaan Pembayaran / Piutang)

* **Relasi:** Memotong saldo tagihan dari *Customer* di modul *AR Invoice* (`amountapplied`).
* **Penjurnalan:** Debit Kas/Bank, Debit Diskon Penjualan (jika ada), Kredit Akun A/R.

---

## 🛡️ 4. Perlindungan Margin, Valuasi, & Produksi

* **Pricelist & Discountlist (*Revenue Control*):** Harga jual divalidasi ke master `Pricelist` yang aktif berdasarkan tanggal efektif. Diskon baris divalidasi batas atasnya melawan `Discountlist`.
* **MCurrency & Exchangerate:** Mengelola nilai tukar transaksi mata uang asing dengan sistem *anti-collision*.
* **Costing & Valuasi Produksi:** 
  * *Pemakaian Bahan (IP):* Debit akun WIP, Kredit akun Inventori Bahan Baku berdasarkan unit cost.
  * *Penerimaan Produksi (RP):* Debit akun Inventori Barang Jadi, Kredit akun Clearing WIP dengan validasi toleransi produksi dan generate nomor *Batch* otomatis.
  * *HPP Penjualan (DO / Loading):* Debit akun HPP (COGS), Kredit inventori/stok dalam perjalanan.

---

## 📊 5. Pemetaan 26 Akun (*COA Mapping*) pada Level Item Group

Bagi tim Accounting, pemahaman terhadap 26 parameter akun di menu *Item Group* sangat krusial untuk memastikan laporan keuangan akurat:

### 🏷️ A. Siklus Penjualan (Sales & Revenue)
1. **REVENUE ACCOUNT:** Akun pendapatan utama yang dikredit saat *A/R Invoice* diterbitkan.
2. **COST OF GOODS SOLD ACCOUNT:** Akun HPP yang didebet saat *Delivery Order* (DO) tereksekusi.
3. **SALES RETURN ACCOUNT:** Akun pengurang pendapatan apabila pelanggan meretur barang.
4. **SALES CREDIT ACCOUNT:** Akun potongan harga/diskon penjualan khusus di luar potongan baris.
5. **SHIPPED GOODS ACCOUNT:** Akun transit persediaan ketika barang sudah dikirim namun belum difaktur (*A/R Invoice*).
6. **VAT OUT ACCOUNT:** Akun PPN Keluaran yang dikredit saat *A/R Invoice* diterbitkan ke pelanggan.

### 🛒 B. Siklus Pembelian & Persediaan (Purchasing & Inventory)
7. **INVENTORY ACCOUNT:** Akun aset persediaan riil di Neraca (didebet saat GRPO, dikredit saat DO/Issue).
8. **EXPENSE ACCOUNT:** Akun beban langsung untuk pembelian barang non-inventori atau jasa.
9. **GOODS CLEARING ACCOUNT:** Akun penyeimbang (*GRNI*) saat GRPO sebelum tagihan vendor diterima.
10. **PURCHASE CREDIT ACCOUNT:** Akun potongan atau memo kredit dari *Supplier*.
11. **PRICE DIFFERENCE ACCOUNT:** Menampung selisih harga antara saat GRPO dengan saat *A/P Invoice*.
12. **VAT IN ACCOUNT:** Akun PPN Masukan yang didebet saat menerima tagihan/pembelian berpajak.

### 🚚 C. Penyesuaian & Transit Logistik
13. **STOCK IN TRANSIT ACCOUNT:** Menampung nilai barang dalam proses mutasi antar gudang atau ekspedisi.
14. **G/L INCREASE ACCOUNT:** Akun pendapatan/keuntungan (*Gain*) hasil surplus *Stock Opname*.
15. **G/L DECREASE ACCOUNT:** Akun beban/kerugian (*Loss*) hasil defisit/hilang *Stock Opname*.
16. **INVENTORY OFFSET INCREASE ACCOUNT:** Akun perantara untuk penyesuaian kenaikan stok manual.
17. **INVENTORY OFFSET DECREASE ACCOUNT:** Akun perantara untuk penyesuaian penurunan stok manual.
18. **NEGATIVE INVENTORY ACCOUNT:** Akun penyeimbang apabila sistem mengizinkan transaksi minus stok sementara.
19. **INVENTORY OFFSET ACCOUNT:** Akun perantara untuk saldo awal persediaan (*Opening Balance*).
20. **EXPENSE CLEAR ACCOUNT:** Akun clearing untuk beban operasional yang belum ditagih.

### 🏭 D. Siklus Pabrikasi (Manufacturing)
21. **WIP INVENTORY ACCOUNT:** Akun Barang Dalam Proses (didebet saat bahan baku ditarik ke produksi).
22. **WIP OFFSET ACCOUNT:** Akun lawan/clearing saat material masuk lini produksi.
23. **WIP INVENTORY VARIANCE ACCOUNT:** Menampung selisih biaya material standar vs aktual saat *Work Order* ditutup.

### ⚙️ E. Akun Sistem, Alokasi, & Selisih Kurs
24. **EXCHANGE RATE ACCOUNT:** Menampung laba/rugi selisih kurs mata uang asing (*Realized/Unrealized Gain & Loss*).
25. **ALLOCATION ACCOUNT:** Menampung biaya tambahan (*Landed Cost* seperti ongkir/bea masuk).
26. **VARIANCE ACCOUNT:** Akun selisih umum akibat metode valuasi rata-rata bergerak (*Moving Average*).

---

## 🔗 6. Integrasi Modul Transaksi & Peran Kerja Tim FAT

### 🏷️ a. Pengendalian Harga & Diskon Penjualan (`MSalesquotation` & `MPricelist`)

* **Peran FAT:** Mengawasi kepatuhan margin penjualan. Sistem melalui `MPricelist` dan `MDiscountlist` menolak otomatis jika harga jual di bawah standar atau diskon melebihi batas maksimal persetujuan.
* **Validasi Pajak:** Menggunakan parameter `taxclude` (0 untuk *Exclude*, 1 untuk *Include*) untuk memisahkan Dasar Pengenaan Pajak (DPP) dan nilai PPN secara presisi.

### 📥 b. Manajemen Piutang & Penagihan (`MArinvoice` & `MIncomingpayment`)

* **A/R Invoice (`MArinvoice`):** Mengakui tagihan ke pelanggan berdasarkan referensi *Delivery Order* (`MDeliveryorder`) atau *Packing* (`MPacking`).
* **Incoming Payment (`MIncomingpayment`):** Mengelola alokasi pelunasan piutang ke nomor dokumen faktur terkait (`amountapplied`). Mengurangi saldo terutang secara real-time dan menjurnal penerimaan kas/bank.

### 📋 c. Manajemen Hutang & Pembelian (`MPurchaserequest`, `MPurchaseorder`, `MGrpo`, `MApinvoice`, `MOutgoingpayment`)

* **Siklus Pengadaan:** Dimulai dari permintaan pembelian (`MPurchaserequest`), pemesanan (`MPurchaseorder`), hingga penerimaan fisik barang (`MGrpo`) yang memicu *Goods Clearing*.
* **A/P Invoice (`MApinvoice`):** Bagian Accounting mencocokkan tagihan vendor dengan GRPO, memproses PPN Masukan (`VAT In`), dan menjurnal hutang dagang.
* **Outgoing Payment (`MOutgoingpayment`):** Melakukan pembayaran ke vendor berdasarkan sisa tagihan (*Balance Due*), memperbarui status `paidamount`, dan menutup kewajiban.

### 📦 d. Logistik, Staging, & Distribusi (`MPacking` & `MLoading`)

* **Packing (`MPacking`):** Memindahkan inventori fisik dari gudang utama ke gudang staging persiapan pengiriman (`STP`) tanpa menghapus nilai aset secara akuntansi.
* **Loading (`MLoading`):** Memvalidasi manifest pengiriman, nomor plat kendaraan, dan jenis ekspedisi sebelum memicu pencatatan HPP dan perpindahan ke status barang terkirim.

### 🏭 e. Akuntansi Manufaktur & Biaya Produksi (`MIssueproduction` & `MReceiptproduction`)

* **Issue for Production (`MIssueproduction`):** Menjurnal perpindahan material dari gudang bahan baku ke akun WIP.
* **Receipt from Production (`MReceiptproduction`):** Menerima barang jadi dari pabrik, melakukan generate nomor *Batch* otomatis, dan menutup Work Order dengan memperhitungkan toleransi produksi.

---

## 🧩 7. Rincian Tambahan Modul Inti (Berdasarkan Spesifikasi Sistem)

Sebagai pelengkap arsitektur operasional FAT di atas, pembagian fungsionalitas sistem pada tingkat modul utama dijabarkan sebagai berikut:

* **💰 SH-FIN (Modul Finance):**
  Berfokus pada pengelolaan operasional keuangan harian yang mencakup:
  * Dashboard keuangan interaktif
  * *Account Receivable* (Piutang & *Aging*)
  * *Account Payable* (Hutang & Pembayaran)
  * *Cash Management* (Kas, *Bank Ledger*, & *Fund Transfer*)
  * *Budget & Funds Management*
  * *Travel Management* (pengajuan & *reimburse* perjalanan dinas/lapangan).

* **📖 SH-ACC (Modul Accounting):**
  Berfokus pada pencatatan akuntansi, buku besar, dan pelaporan yang mencakup:
  * *Chart of Account* (CoA)
  * *Journal Entry*
  * *General Ledger* (*New General Ledger* & *Special Purpose Ledger*)
  * Arus Kas (*Cash Flow*)
  * Valuasi Persediaan (*Inventory Valuation*)
  * Akuntansi Aset Tetap (*Fixed Asset / Asset Accounting*)
  * Manajemen Pajak (*Tax Management*)
  * Tutup Buku Periode (*Period Closing*)
  * Laporan Keuangan (*Financial Report* - Neraca & Laba Rugi).