# Panduan Operasional & Teknis Modul Finance, Accounting, & Tax (FAT)
**Sistem ERP Enterprise Terintegrasi**

Dokumen ini dirancang khusus untuk memandu tim **Finance, Accounting, & Tax (FAT)** dalam memahami alur kerja sistem, titik kendali (*control points*), penjurnalan otomatis (*auto-journaling*), serta pemetaan akun buku besar yang terintegrasi langsung dengan modul logistik, pembelian, penjualan, dan produksi.

---

## 1. Arsitektur Mesin Akuntansi Otomatis (`MJournal`)
Sistem ini menggunakan pendekatan *Subledger-to-General Ledger Automation*. Tim FAT tidak perlu menginput jurnal umum secara manual untuk transaksi harian rutin. Seluruh dokumen operasional akan memicu pembentukan jurnal ganda (*Double-Entry Bookkeeping*) secara otomatis melalui `MJournal`.

### a. 10 Sumber Transaksi Akuntansi Utama (`source`)
1. **GRPO (*Goods Receipt PO*):** Mengakui persediaan masuk dan kewajiban sementara (*Goods Clearing*).
2. **APINV (*A/P Invoice*):** Mengakui Hutang Dagang resmi dari *Supplier* sekaligus melikuidasi akun *Goods Clearing*.
3. **DO (*Delivery Order*):** Mengeluarkan fisik barang dari inventori dan mengakui Harga Pokok Penjualan (HPP / COGS).
4. **ARINV (*A/R Invoice*):** Mengakui Piutang Usaha dan Pendapatan Penjualan (*Revenue*).
5. **OUTPAY (*Outgoing Payment*):** Pencatatan pengeluaran kas/bank untuk melunasi Hutang Dagang (`MApinvoice`).
6. **INPAY (*Incoming Payment*):** Pencatatan penerimaan kas/bank dari pelunasan Piutang Usaha (`MArinvoice`).
7. **IP (*Issue for Production*):** Mengeluarkan bahan baku ke lini produksi (WIP vs Persediaan Bahan Baku).
8. **RP (*Receipt from Production*):** Menerima barang jadi dari pabrik ke gudang (Persediaan Barang Jadi vs WIP Clearing).
9. **LOADING (*Loading & Ekspedisi*):** Memindahkan nilai inventori dari gudang *Staging* (*Packing*) ke status barang terkirim (*Shipped Goods*).
10. **STOCK_OPNAME:** Menyesuaikan nilai persediaan fisik terhadap selisih kurang/lebih (*Gain/Loss*).

### b. Mekanisme Pemetaan Akun Dinamis (*Dynamic COA Mapping*)
Sistem membaca hierarki akun secara dinamis dengan alur:
$$\text{Transaksi Detail} \rightarrow \text{Item Master} \rightarrow \text{Item Group} \rightarrow \text{Parameter Akun (26 COA)}$$
Hal ini memastikan bahwa setiap kategori produk (misalnya Bahan Baku vs Barang Jadi) masuk ke pos neraca atau laba rugi yang tepat tanpa intervensi manual.

---

## 2. Pemetaan 26 Akun (*COA Mapping*) pada Level Item Group
Bagi tim Accounting, pemahaman terhadap 26 parameter akun di menu *Item Group* sangat krusial untuk memastikan laporan keuangan (*Balance Sheet & Income Statement*) akurat:

### A. Siklus Penjualan (Sales & Revenue)
1. **REVENUE ACCOUNT:** Akun pendapatan utama yang dikredit saat *A/R Invoice* diterbitkan.
2. **COST OF GOODS SOLD ACCOUNT:** Akun HPP yang didebet saat *Delivery Order* (DO) tereksekusi.
3. **SALES RETURN ACCOUNT:** Akun pengurang pendapatan apabila pelanggan meretur barang.
4. **SALES CREDIT ACCOUNT:** Akun potongan harga/diskon penjualan khusus di luar potongan baris.
5. **SHIPPED GOODS ACCOUNT:** Akun transit persediaan ketika barang sudah dikirim namun belum difaktur (*A/R Invoice*).
6. **VAT OUT ACCOUNT:** Akun PPN Keluaran yang dikredit saat *A/R Invoice* diterbitkan ke pelanggan.

### B. Siklus Pembelian & Persediaan (Purchasing & Inventory)
7. **INVENTORY ACCOUNT:** Akun aset persediaan riil di Neraca (didebet saat GRPO, dikredit saat DO/Issue).
8. **EXPENSE ACCOUNT:** Akun beban langsung untuk pembelian barang non-inventori atau jasa.
9. **GOODS CLEARING ACCOUNT:** Akun penyeimbang (*GRNI*) saat GRPO sebelum tagihan vendor diterima.
10. **PURCHASE CREDIT ACCOUNT:** Akun potongan atau memo kredit dari *Supplier*.
11. **PRICE DIFFERENCE ACCOUNT:** Menampung selisih harga antara saat GRPO dengan saat *A/P Invoice*.
12. **VAT IN ACCOUNT:** Akun PPN Masukan yang didebet saat menerima tagihan/pembelian berpajak.

### C. Penyesuaian & Transit Logistik
13. **STOCK IN TRANSIT ACCOUNT:** Menampung nilai barang dalam proses mutasi antar gudang atau ekspedisi.
14. **G/L INCREASE ACCOUNT:** Akun pendapatan/keuntungan (*Gain*) hasil surplus *Stock Opname*.
15. **G/L DECREASE ACCOUNT:** Akun beban/kerugian (*Loss*) hasil defisit/hilang *Stock Opname*.
16. **INVENTORY OFFSET INCREASE / DECREASE ACCOUNT:** Akun lawan untuk penyesuaian stok manual non-pembelian/penjualan.
17. **NEGATIVE INVENTORY ACCOUNT:** Akun penyeimbang apabila sistem mengizinkan transaksi minus stok sementara.
18. **INVENTORY OFFSET ACCOUNT:** Akun perantara untuk saldo awal persediaan (*Opening Balance*).

### D. Siklus Pabrikasi (Manufacturing)
19. **WIP INVENTORY ACCOUNT:** Akun Barang Dalam Proses (didebet saat bahan baku ditarik ke produksi).
20. **WIP OFFSET ACCOUNT:** Akun lawan/clearing saat material masuk lini produksi.
21. **WIP INVENTORY VARIANCE ACCOUNT:** Menampung selisih biaya material standar vs aktual saat *Work Order* ditutup.

### E. Akun Sistem & Alokasi Tambahan
22. **EXCHANGE RATE ACCOUNT:** Menampung laba/rugi selisih kurs mata uang asing (*Realized/Unrealized Gain & Loss*).
23. **ALLOCATION ACCOUNT:** Menampung biaya tambahan (*Landed Cost* seperti ongkir/bea masuk).
24. **VARIANCE ACCOUNT:** Akun selisih umum akibat metode valuasi rata-rata bergerak (*Moving Average*).
25. **EXPENSE CLEAR ACCOUNT:** Akun clearing untuk beban operasional yang belum ditagih.

---

## 3. Integrasi Modul Transaksi & Peran Kerja Tim FAT

### a. Pengendalian Harga & Diskon Penjualan (`MSalesquotation` & `MPricelist`)
* **Peran FAT:** Mengawasi kepatuhan margin penjualan. Sistem melalui `MPricelist` dan `MDiscountlist` menolak otomatis jika harga jual di bawah standar atau diskon melebihi batas maksimal persetujuan.
* **Validasi Pajak:** Menggunakan parameter `taxclude` (0 untuk *Exclude*, 1 untuk *Include*) untuk memisahkan Dasar Pengenaan Pajak (DPP) dan nilai PPN secara presisi.

### b. Manajemen Piutang & Penagihan (`MArinvoice` & `MIncomingpayment`)
* **A/R Invoice (`MArinvoice`):** Mengakui tagihan ke pelanggan berdasarkan referensi *Delivery Order* (`MDeliveryorder`) atau *Packing* (`MPacking`).
* **Incoming Payment (`MIncomingpayment`):** Mengelola alokasi pelunasan piutang ke nomor dokumen faktur terkait (`amountapplied`). Mengurangi saldo terutang secara real-time dan menjurnal penerimaan kas/bank.

### c. Manajemen Hutang & Pembelian (`MPurchaserequest`, `MPurchaseorder`, `MGrpo`, `MApinvoice`, `MOutgoingpayment`)
* **Siklus Pengadaan:** Dimulai dari permintaan pembelian (`MPurchaserequest`), pemesanan (`MPurchaseorder`), hingga penerimaan fisik barang (`MGrpo`) yang memicu *Goods Clearing*.
* **A/P Invoice (`MApinvoice`):** Bagian Accounting mencocokkan tagihan vendor dengan GRPO, memproses PPN Masukan (`VAT In`), dan menjurnal hutang dagang.
* **Outgoing Payment (`MOutgoingpayment`):** Melakukan pembayaran ke vendor berdasarkan sisa tagihan (*Balance Due*), memperbarui status `paidamount`, dan menutup kewajiban.

### d. Logistik, Staging, & Distribusi (`MPacking` & `MLoading`)
* **Packing (`MPacking`):** Memindahkan inventori fisik dari gudang utama ke gudang staging persiapan pengiriman (`STP`) tanpa menghapus nilai aset secara akuntansi.
* **Loading (`MLoading`):** Memvalidasi manifest pengiriman, nomor plat kendaraan, dan jenis ekspedisi sebelum memicu pencatatan HPP dan perpindahan ke status barang terkirim.

### e. Akuntansi Manufaktur & Biaya Produksi (`MIssueproduction` & `MReceiptproduction`)
* **Issue for Production (`MIssueproduction`):** Menjurnal perpindahan material dari gudang bahan baku ke akun WIP.
* **Receipt from Production (`MReceiptproduction`):** Menerima barang jadi dari pabrik, melakukan generate nomor *Batch* otomatis, dan menutup Work Order dengan memperhitungkan toleransi produksi.