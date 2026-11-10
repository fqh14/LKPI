# 📚 Modul Finance, Accounting & Tax (FAT)
## Sistem ERP Enterprise Terintegrasi

Dokumen ini memuat arsitektur menyeluruh, alur data, penjabaran teknis, serta panduan operasional dari modul Finance, Accounting, and Tax (FAT). Dokumen ini dirancang untuk memandu tim FAT dalam memahami otomatisasi penjurnalan, titik kendali (*control points*), manajemen pajak, serta pemetaan akun buku besar secara komprehensif.

---

## Overview Modul FAT

Modul **Finance, Accounting & Tax (FAT)** merupakan tulang punggung sistem ERP yang mengelola seluruh siklus keuangan perusahaan — mulai dari operasional harian, pencatatan akuntansi *double-entry*, hingga kepatuhan pajak. Modul ini terbagi menjadi dua sub-modul utama:

| Kode Modul | Nama Modul | Fokus Utama |
|---|---|---|
| **SH-FIN** | Finance | Operasional keuangan harian, kas, piutang, hutang, budget, travel |
| **SH-ACC** | Accounting | Pencatatan akuntansi, buku besar, pelaporan, valuasi, aset tetap, pajak |

> 🎯 **Prinsip Utama:** *Subledger-to-General Ledger Automation* — seluruh transaksi operasional di modul lain (Sales, Purchasing, Inventory, Production) secara otomatis menghasilkan jurnal akuntansi melalui engine `MJournal` tanpa input manual.

---

## SH-FIN — Modul Finance

> **Fokus:** Pengelolaan operasional keuangan harian yang mencakup dashboard keuangan, piutang, hutang, kas & bank, budget, serta travel management.

### 1. 📊 Dashboard Keuangan Interaktif
| Komponen | Penjabaran |
|---|---|
| **Cash Position** | Posisi kas & bank real-time across semua rekening perusahaan. |
| **A/R Aging Summary** | Ringkasan piutang usaha berdasarkan kategori umur (current, 1-30, 31-60, 61-90, >90 hari). |
| **A/P Aging Summary** | Ringkasan hutang usaha berdasarkan jatuh tempo pembayaran. |
| **Revenue vs Budget** | Perbandingan realisasi pendapatan terhadap budget yang diapprove. |
| **Expense vs Budget** | Monitoring pengeluaran per cost center/divisi terhadap alokasi budget. |
| **Cash Flow Forecast** | Proyeksi arus kas masuk dan keluar untuk 30/60/90 hari ke depan. |
| **KPI Keuangan** | DSO (Days Sales Outstanding), DPO (Days Payable Outstanding), Working Capital Ratio, Current Ratio. |
| **Alert & Notifikasi** | Peringatan otomatis untuk invoice jatuh tempo, budget over-run, cash di bawah threshold. |
| **Multi-currency View** | Tampilan dalam mata uang lokal dan foreign currency dengan kurs real-time. |
| **Drill-down Capability** | Kemampuan drill-down dari ringkasan ke detail transaksi asal. |

### 2. 📥 Account Receivable (Piutang & Aging)
#### 2.1 Master Pelanggan & Kredit
| Komponen | Penjabaran |
|---|---|
| **Customer Master** | Data lengkap pelanggan: NPWP, alamat tagih, alamat kirim, payment term, credit limit. |
| **Credit Scoring** | Penilaian kelayakan kredit berdasarkan history pembayaran dan laporan keuangan. |
| **Credit Limit Management** | Pengaturan limit kredit per pelanggan dengan alert otomatis saat mendekati limit. |
| **Payment Term** | Konfigurasi syarat pembayaran: COD, NET 30/60/90, EOM, Proforma. |

#### 2.2 A/R Invoice (`MArinvoice`)
| Komponen | Penjabaran |
|---|---|
| **Sumber Invoice** | Dibuat dari referensi Delivery Order (`MDeliveryorder`) atau Packing (`MPacking`). |
| **Validasi Otomatis** | Sistem memvalidasi harga terhadap `Pricelist` aktif dan diskon terhadap `Discountlist`. |
| **Perhitungan Pajak** | Otomatis menghitung PPN Keluaran (VAT Out) berdasarkan parameter `taxclude`. |
| **Multi-currency** | Support invoice dalam mata uang asing dengan konversi kurs otomatis. |
| **Status Invoice** | Draft, Posted, Partial Paid, Paid, Cancelled, Write-off. |
| **Auto-Journal** | Saat posting: Debit Piutang Usaha, Kredit Revenue, Kredit VAT Out. |

#### 2.3 Incoming Payment (`MIncomingpayment`)
| Komponen | Penjabaran |
|---|---|
| **Penerimaan Pembayaran** | Pencatatan penerimaan kas/bank dari pelanggan. |
| **Alokasi Pembayaran** | Fitur `amountapplied` untuk mengalokasikan pembayaran ke nomor invoice spesifik. |
| **Multi-Invoice Payment** | Satu pembayaran dapat melunasi beberapa invoice sekaligus. |
| **Partial Payment** | Support pembayaran sebagian dengan tracking saldo tersisa (Balance Due). |
| **Payment Method** | Transfer bank, cek, giro, kas, kartu kredit, e-wallet. |
| **Auto-Journal** | Debit Kas/Bank, Debit Diskon Penjualan (jika ada), Kredit Piutang Usaha. |
| **Rekonsiliasi Bank** | Matching otomatis antara pembayaran tercatat dengan mutasi bank. |

#### 2.4 A/R Aging & Collection
| Komponen | Penjabaran |
|---|---|
| **Aging Report** | Laporan umur piutang: Current, 1-30, 31-60, 61-90, 91-120, >120 hari. |
| **Collection Workflow** | Tracking upaya penagihan: reminder email, surat teguran, call log, visit log. |
| **Dunning Letter** | Generate otomatis surat peringatan bertingkat (1st, 2nd, 3rd notice). |
| **Bad Debt Provision** | Pencadangan piutang ragu-ragu berdasarkan kebijakan perusahaan. |
| **Write-off** | Penghapusan piutang tak tertagih dengan approval workflow bertingkat. |
| **DSO Tracking** | Monitoring Days Sales Outstanding per pelanggan dan secara agregat. |

### 3. 📤 Account Payable (Hutang & Pembayaran)
#### 3.1 Siklus Pengadaan
| Tahap | Modul | Penjabaran |
|---|---|---|
| **Permintaan** | `MPurchaserequest` | Pengajuan kebutuhan pembelian oleh user department. |
| **Persetujuan** | Approval Workflow | Validasi budget dan otorisasi sesuai matrix approval. |
| **Pemesanan** | `MPurchaseorder` | PO resmi ke supplier dengan detail item, qty, harga, delivery schedule. |
| **Penerimaan** | `MGrpo` | Goods Receipt PO — penerimaan fisik barang, memicu Goods Clearing. |
| **Tagihan** | `MApinvoice` | Invoice dari supplier, pencocokan 3-way match (PO-GRPO-Invoice). |
| **Pembayaran** | `MOutgoingpayment` | Pelunasan hutang berdasarkan Balance Due. |

#### 3.2 A/P Invoice (`MApinvoice`)
| Komponen | Penjabaran |
|---|---|
| **3-Way Matching** | Validasi otomatis antara PO, GRPO, dan Invoice supplier. |
| **Tolerance Check** | Toleransi perbedaan harga dan quantity yang diperbolehkan. |
| **Pajak Masukan** | Otomatis mencatat PPN Masukan (VAT In) dari invoice supplier. |
| **Multi-currency** | Support invoice supplier dalam mata uang asing. |
| **Auto-Journal** | Debit Goods Clearing / Expense, Debit VAT In, Kredit Hutang Dagang. |
| **Status Invoice** | Draft, Posted, Partial Paid, Paid, On Hold, Cancelled. |

#### 3.3 Outgoing Payment (`MOutgoingpayment`)
| Komponen | Penjabaran |
|---|---|
| **Pembayaran Hutang** | Pencatatan pengeluaran kas/bank untuk melunasi hutang vendor. |
| **Payment Run** | Batch payment untuk multiple invoice dalam satu proses pembayaran. |
| **Payment Method** | Transfer bank (batch file), cek, giro, kas. |
| **Discount Taking** | Pencatatan diskon pembayaran early payment dari supplier. |
| **Withholding Tax** | Pemotongan PPh Pasal 23/4(2) saat pembayaran ke vendor. |
| **Auto-Journal** | Debit Hutang Dagang, Debit Diskon Pembelian (jika ada), Kredit Kas/Bank. |
| **Payment Schedule** | Penjadwalan pembayaran berdasarkan due date untuk optimalisasi cash flow. |

#### 3.4 A/P Aging & Vendor Management
| Komponen | Penjabaran |
|---|---|
| **Aging Report** | Laporan umur hutang per vendor dan kategori jatuh tempo. |
| **Payment Priority** | Prioritas pembayaran berdasarkan criticality vendor dan discount term. |
| **Vendor Statement** | Rekonsiliasi statement bulanan dengan vendor. |
| **DPO Tracking** | Monitoring Days Payable Outstanding untuk optimisasi working capital. |
| **WHT Reporting** | Laporan pemotongan pajak untuk kepatuhan regulasi. |

### 4. 💵 Cash Management (Kas, Bank Ledger, & Fund Transfer)
#### 4.1 Cash Management
| Komponen | Penjabaran |
|---|---|
| **Petty Cash** | Pengelolaan kas kecil dengan voucher system dan limit replenishment. |
| **Cash Receipt** | Pencatatan penerimaan kas dari berbagai sumber. |
| **Cash Disbursement** | Pencatatan pengeluaran kas dengan approval workflow. |
| **Cash Count** | Opname kas fisik dengan rekonsiliasi terhadap saldo sistem. |
| **Cash Flow Daily** | Laporan arus kas harian untuk monitoring likuiditas. |

#### 4.2 Bank Ledger
| Komponen | Penjabaran |
|---|---|
| **Bank Account Master** | Data rekening bank: nama bank, nomor rekening, currency, account type. |
| **Bank Statement Import** | Import mutasi bank dalam format CSV/MT940/CAMT.053. |
| **Auto-Reconciliation** | Matching otomatis transaksi sistem dengan mutasi bank. |
| **Manual Reconciliation** | Rekonsiliasi manual untuk transaksi yang tidak match otomatis. |
| **Bank Charges** | Pencatatan biaya administrasi bank dan provisi. |
| **Interest Calculation** | Perhitungan bunga bank (deposito/kredit) secara otomatis. |
| **Multi-bank View** | Dashboard consolidated untuk semua rekening bank perusahaan. |

#### 4.3 Fund Transfer
| Komponen | Penjabaran |
|---|---|
| **Internal Transfer** | Transfer dana antar rekening bank internal perusahaan. |
| **Transfer Approval** | Workflow persetujuan untuk transfer dengan threshold tertentu. |
| **Transfer Journal** | Auto-journal: Debit Bank Tujuan, Kredit Bank Asal. |
| **Transfer Schedule** | Penjadwalan transfer rutin (payroll, supplier payment). |
| **FX Transfer** | Transfer antar mata uang dengan konversi kurs dan recording gain/loss. |

### 5. 📈 Budget & Funds Management
#### 5.1 Budget Planning
| Komponen | Penjabaran |
|---|---|
| **Budget Template** | Template anggaran per cost center, project, atau department. |
| **Top-down & Bottom-up** | Support pendekatan budget dari atas ke bawah atau dari unit ke korporat. |
| **Budget Versioning** | Multiple versi budget: Draft, Proposed, Approved, Final. |
| **Budget Hierarchy** | Hierarki budget: Corporate → Division → Department → Cost Center. |
| **Period Breakdown** | Breakdown budget tahunan ke bulanan/kuartalan. |

#### 5.2 Budget Control
| Komponen | Penjabaran |
|---|---|
| **Commitment Tracking** | Pencatatan komitmen anggaran (PO, kontrak) sebelum terealisasi. |
| **Actual vs Budget** | Perbandingan real-time antara pengeluaran aktual dengan budget. |
| **Variance Analysis** | Analisis selisih dengan kategori: Favorable, Unfavorable, dengan penjelasan. |
| **Budget Alert** | Notifikasi otomatis saat utilization mencapai 80%, 90%, 100%. |
| **Budget Block** | Pemblokiran transaksi yang melebihi budget (hard/soft control). |
| **Budget Transfer** | Pemindahan alokasi budget antar cost center dengan approval. |
| **Rolling Forecast** | Update forecast budget secara berkala berdasarkan actual terbaru. |

#### 5.3 Funds Management
| Komponen | Penjabaran |
|---|---|
| **Fund Source** | Pelacakan sumber dana: operating cash, loan, equity, grant. |
| **Fund Allocation** | Alokasi dana ke program/project tertentu dengan tracking terpisah. |
| **Fund Utilization** | Monitoring penggunaan dana per sumber dan per alokasi. |
| **Fund Reporting** | Laporan pertanggungjawaban penggunaan dana per periode. |

### 6. ✈️ Travel Management (Pengajuan & Reimburse Perjalanan)
#### 6.1 Travel Request & Approval
| Komponen | Penjabaran |
|---|---|
| **Travel Request Form** | Form pengajuan perjalanan: tujuan, durasi, tujuan bisnis, estimasi biaya. |
| **Itinerary Planning** | Rencana perjalanan lengkap dengan jadwal per hari. |
| **Approval Workflow** | Persetujuan bertingkat: Supervisor → Manager → Finance → Director (tergantung budget). |
| **Travel Policy** | Validasi otomatis terhadap kebijakan perjalanan (hotel max rate, transport class). |
| **Advance Request** | Pengajuan uang muka perjalanan dengan tracking pertanggungjawaban. |

#### 6.2 Expense & Reimbursement
| Komponen | Penjabaran |
|---|---|
| **Expense Entry** | Input pengeluaran aktual selama perjalanan dengan kategori detail. |
| **Receipt Upload** | Upload foto struk/invoice sebagai bukti pengeluaran. |
| **Per Diem Calculation** | Perhitungan otomatis uang harian berdasarkan kota tujuan dan durasi. |
| **Expense Approval** | Workflow persetujuan expense report oleh supervisor dan finance. |
| **Reimbursement** | Proses penggantian pengeluaran yang disetujui via transfer bank/kas. |
| **Advance Settlement** | Rekonsiliasi advance yang diberikan vs pengeluaran aktual (lebih/kurang). |
| **Travel Report** | Laporan lengkap perjalanan: tujuan, hasil, pengeluaran, ROI perjalanan. |

#### 6.3 Travel Analytics
| Komponen | Penjabaran |
|---|---|
| **Cost per Trip** | Analisis biaya rata-rata per perjalanan per destination. |
| **Policy Compliance** | Tingkat kepatuhan terhadap travel policy per employee. |
| **Vendor Preference** | Analisis preferensi vendor (hotel, airline) untuk negosiasi corporate rate. |
| **Budget Tracking** | Monitoring budget travel per department vs actual. |

---

## SH-ACC — Modul Accounting

> **Fokus:** Pencatatan akuntansi, buku besar, pelaporan keuangan, valuasi persediaan, aset tetap, pajak, dan tutup buku periode.

### 1. 📋 Chart of Account (CoA)
| Komponen | Penjabaran |
|---|---|
| **CoA Structure** | Struktur akun berbasis hierarki: Level 1 (Kelompok) → Level 2 (Jenis) → Level 3 (Perkiraan) → Level 4 (Detail). |
| **Account Type** | Aset, Liabilitas, Ekuitas, Pendapatan, Beban, Biaya. |
| **Account Category** | Neraca (Balance Sheet) dan Laba Rugi (Income Statement). |
| **Multi-currency Account** | Support akun dalam multiple currency dengan revaluation otomatis. |
| **Account Grouping** | Pengelompokan akun untuk reporting: Operating, Non-operating, Other. |
| **Account Status** | Active, Inactive, Frozen (tidak boleh ada transaksi baru). |
| **CoA Template** | Template CoA standar industri yang dapat dikustomisasi. |
| **Account Mapping** | Pemetaan akun ke laporan keuangan (mapping rule untuk financial statement). |

### 2. 📝 Journal Entry
#### 2.1 Manual Journal
| Komponen | Penjabaran |
|---|---|
| **Journal Header** | Tanggal, nomor jurnal, referensi, deskripsi, currency, exchange rate. |
| **Journal Lines** | Minimal 2 baris (debet & kredit) dengan akun, amount, description, cost center. |
| **Balancing Check** | Validasi otomatis total debet = total kredit sebelum posting. |
| **Multi-currency** | Support jurnal dalam mata uang asing dengan auto-calculate gain/loss kurs. |
| **Recurring Journal** | Template jurnal berulang (penyusutan, sewa, amortisasi) dengan schedule otomatis. |
| **Reversal Journal** | Jurnal pembalikan otomatis untuk accrual dan deferral. |
| **Approval Workflow** | Persetujuan jurnal manual berdasarkan amount threshold. |

#### 2.2 Auto-Journal dari MJournal
| Sumber Transaksi | Jurnal Otomatis yang Dihasilkan |
|---|---|
| **GRPO** | Debit Inventory, Kredit Goods Clearing |
| **APINV** | Debit Goods Clearing/Expense, Debit VAT In, Kredit A/P |
| **DELIVERY ORDER** | Debit COGS, Kredit Inventory |
| **AR INVOICE** | Debit A/R, Kredit Revenue, Kredit VAT Out |
| **OUTGOING PAYMENT** | Debit A/P, Debit Purchase Discount, Kredit Cash/Bank |
| **INCOMING PAYMENT** | Debit Cash/Bank, Debit Sales Discount, Kredit A/R |
| **ISSUE PRODUCTION** | Debit WIP, Kredit Raw Material Inventory |
| **RECEIPT PRODUCTION** | Debit Finished Goods Inventory, Kredit WIP Clearing |
| **LOADING** | Debit Shipped Goods/COGS, Kredit Staging Inventory |
| **STOCK OPNAME** | Debit/Credit Inventory, Kredit/Debit Gain/Loss |
| **AR CREDIT MEMO** | Debit Sales Return, Debit VAT Out (Pajak Keluaran), Kredit A/R. (Disertai jurnal balik inventori: Debit Inventory, Kredit COGS jika fisik barang masuk kembali ke gudang) |
| **AP CREDIT MEMO** | Debit A/P (Hutang Dagang), Kredit Inventory/Goods Clearing, Kredit VAT In (Pajak Masukan)|
| **LANDED COST** | Debit Inventory (menambah nilai valuasi barang), Kredit Freight Clearing / Expense Clear / A/P Vendor Ekspedisi. Berfungsi mendistribusikan biaya kirim atau bea masuk ke harga pokok barang|
| **WASTE** | Debit Production Variance / Scrap Expense, Kredit WIP Inventory. Digunakan saat ada material yang susut, rusak, atau terbuang di lini produksi di luar toleransi normal.|
| **VARIANCE ADJUSTMENT** | Menyesuaikan selisih antara estimasi biaya standar (Standard Cost) dengan biaya aktual (Actual Cost) saat Work Order ditutup.|
| **FIXED ASSET DEPRECIATION** | Debit Depreciation Expense (Beban Penyusutan), Kredit Accumulated Depreciation (Akumulasi Penyusutan)|
| **AR DOWN PAYMENT** | Debit Cash/Bank, Kredit Customer Deposit (Akun Kewajiban/Liability|
| **AP DOWN PAYMENT** | Debit Vendor Advance (Akun Aset Lancar), Kredit Cash/Bank|
| **EXCHANGE REVALUATION** | Menyesuaikan nilai sisa piutang/hutang valuta asing terhadap kurs tengah Bank Indonesia per akhir bulan (Debit/Kredit Unrealized Gain/Loss terhadap A/R atau A/P)|

### 3. 📖 General Ledger
#### 3.1 New General Ledger
| Komponen | Penjabaran |
|---|---|
| **Ledger Structure** | Buku besar utama dengan dimensi: Akun, Cost Center, Project, Product, Area. |
| **Real-time Posting** | Update saldo akun secara real-time saat jurnal diposting. |
| **Multi-dimensional** | Analisis saldo berdasarkan multiple dimensi secara bersamaan. |
| **Drill-down** | Dari saldo akun → jurnal → dokumen sumber → detail transaksi. |
| **Period Management** | Pembukaan dan penutupan periode akuntansi dengan kontrol ketat. |
| **Audit Trail** | Jejak audit lengkap: siapa, kapan, apa yang diubah. |

#### 3.2 Special Purpose Ledger
| Komponen | Penjabaran |
|---|---|
| **Management Ledger** | Buku besar untuk kepentingan manajemen dengan struktur berbeda dari statutory. |
| **Tax Ledger** | Buku besar khusus untuk pelaporan pajak dengan penyesuaian fiskal. |
| **Consolidation Ledger** | Buku besar untuk konsolidasi laporan keuangan grup perusahaan. |
| **Segment Reporting** | Ledger per segmen bisnis untuk pelaporan segmen (PSAK 5/IFRS 8). |
| **Custom Ledger** | Ledger custom untuk kebutuhan khusus (CSR, environmental, sustainability). |

### 4. 💧 Arus Kas (Cash Flow)
| Komponen | Penjabaran |
|---|---|
| **Cash Flow Statement** | Laporan arus kas metode langsung dan tidak langsung (PSAK 2/IAS 7). |
| **Operating Activities** | Arus kas dari aktivitas operasional: penerimaan dari pelanggan, pembayaran ke supplier. |
| **Investing Activities** | Arus kas dari aktivitas investasi: pembelian/penjualan aset tetap, investasi. |
| **Financing Activities** | Arus kas dari aktivitas pendanaan: pinjaman, dividen, emisi saham. |
| **Direct Method** | Laporan arus kas berdasarkan penerimaan dan pengeluaran kas riil. |
| **Indirect Method** | Laporan arus kas dimulai dari laba bersih dengan penyesuaian non-cash items. |
| **Cash Flow Forecast** | Proyeksi arus kas masa depan berdasarkan pipeline transaksi. |
| **Cash Flow Analysis** | Analisis tren arus kas, rasio likuiditas, working capital management. |

### 5. 📦 Inventory Valuation (Valuasi Persediaan)
| Komponen | Penjabaran |
|---|---|
| **Valuation Methods** | Support berbagai metode: Moving Average, FIFO, Standard Cost, Specific Identification. |
| **Moving Average** | Perhitungan rata-rata bergerak otomatis setiap kali ada penerimaan barang. |
| **FIFO** | First In First Out — barang yang masuk pertama dikeluarkan pertama. |
| **Standard Cost** | Biaya standar dengan pencatatan variance (purchase price variance, production variance). |
| **Lower of Cost or NRV** | Valuasi berdasarkan nilai terendah antara biaya perolehan dan nilai realisasi neto. |
| **Inventory Revaluation** | Penyesuaian nilai persediaan akibat perubahan kurs atau harga pasar. |
| **Landed Cost** | Alokasi biaya tambahan (ongkir, bea masuk, asuransi) ke nilai persediaan. |
| **Inventory Aging** | Analisis umur persediaan untuk identifikasi slow-moving dan obsolete items. |
| **Stock Opname** | Pencatatan hasil opname fisik dengan auto-generate jurnal selisih (Gain/Loss). |

### 6. 🏢 Fixed Asset / Asset Accounting
#### 6.1 Asset Master
| Komponen | Penjabaran |
|---|---|
| **Asset Registration** | Pencatatan aset tetap: kode, nama, kategori, lokasi, tanggal perolehan, nilai perolehan. |
| **Asset Category** | Kategori aset: tanah, bangunan, kendaraan, mesin, peralatan, furniture. |
| **Depreciation Method** | Metode penyusutan: Straight-line, Declining balance, Sum of years digits, Units of production. |
| **Useful Life** | Masa manfaat aset sesuai kebijakan akuntansi dan regulasi pajak. |
| **Salvage Value** | Nilai sisa aset di akhir masa manfaat. |

#### 6.2 Depreciation & Disposal
| Komponen | Penjabaran |
|---|---|
| **Auto Depreciation** | Perhitungan dan pencatatan penyusutan otomatis setiap periode. |
| **Depreciation Schedule** | Jadwal penyusutan lengkap per aset untuk seluruh masa manfaat. |
| **Asset Transfer** | Pemindahan aset antar lokasi/cost center dengan tracking history. |
| **Asset Disposal** | Pencatatan penjualan/penghapusan aset dengan perhitungan gain/loss. |
| **Asset Impairment** | Penurunan nilai aset dengan pencatatan impairment loss. |
| **Asset Revaluation** | Penilaian kembali aset tetap sesuai nilai wajar. |
| **Asset Report** | Laporan daftar aset, akumulasi penyusutan, nilai buku, depreciation expense. |

### 7. 🧾 Tax Management (Manajemen Pajak)
#### 7.1 PPN (VAT)
| Komponen | Penjabaran |
|---|---|
| **VAT In (Pajak Masukan)** | Otomatis dicatat saat GRPO atau A/P Invoice dari supplier. |
| **VAT Out (Pajak Keluaran)** | Otomatis dicatat saat A/R Invoice ke pelanggan. |
| **VAT Calculation** | Perhitungan berdasarkan parameter `taxclude`: Exclude (DPP = Subtotal - Diskon) atau Include (DPP = Grand Total / (1 + Rate)). |
| **VAT Reporting** | Laporan SPT Masa PPN dengan rekonsiliasi VAT In vs VAT Out. |
| **VAT Credit/Debit Note** | Pencatatan koreksi PPN melalui credit/debit note. |
| **e-Faktur Integration** | Integrasi dengan sistem e-Faktur DJP untuk pelaporan PPN. |

#### 7.2 PPh (Income Tax)
| Komponen | Penjabaran |
|---|---|
| **PPh Pasal 21** | Pemotongan pajak penghasilan karyawan dengan perhitungan progresif. |
| **PPh Pasal 23** | Pemotongan pajak atas jasa, sewa, dividen, bunga, royalti. |
| **PPh Pasal 4(2)** | Pajak final atas sewa tanah/bangunan, pengalihan hak, jasa konstruksi. |
| **PPh Pasal 25** | Angsuran PPh badan bulanan. |
| **PPh Pasal 29** | PPh terutang tahunan setelah dikreditkan dengan PPh Pasal 25. |
| **Withholding Tax** | Pemotongan pajak saat pembayaran ke vendor dengan tracking dan reporting. |
| **Tax Calendar** | Kalender kewajiban perpajakan dengan reminder jatuh tempo. |

#### 7.3 Tax Compliance
| Komponen | Penjabaran |
|---|---|
| **SPT Masa** | Preparation dan filing SPT Masa PPN, PPh 21, PPh 23, PPh 4(2). |
| **SPT Tahunan** | Preparation SPT Tahunan PPh Badan dan PPh Orang Pribadi. |
| **Transfer Pricing** | Dokumentasi transaksi afiliasi untuk kepatuhan transfer pricing. |
| **Tax Audit Support** | Penyediaan data dan laporan untuk pemeriksaan pajak. |
| **Tax Provision** | Pencadangan pajak tangguhan (deferred tax) sesuai PSAK 46. |

### 8. 📅 Period Closing (Tutup Buku Periode)
| Tahap | Aktivitas | Penjabaran |
|---|---|---|
| **1. Pre-Closing** | Review Transaksi | Memastikan semua transaksi periode berjalan sudah tercatat lengkap. |
| **2. Accrual & Deferral** | Jurnal Penyesuaian | Mencatat accrued expense, prepaid expense, unearned revenue, accrued revenue. |
| **3. Depreciation** | Penyusutan Aset | Menjalankan auto-depreciation untuk semua aset tetap. |
| **4. Inventory Closing** | Tutup Persediaan | Menjalankan valuation dan closing inventory untuk periode. |
| **5. FX Revaluation** | Revaluasi Kurs | Menyesuaikan nilai akun mata uang asing dengan kurs akhir periode. |
| **6. Intercompany** | Eliminasi Transaksi Antar Perusahaan | Rekonsiliasi dan eliminasi transaksi antar entitas grup. |
| **7. Trial Balance** | Neraca Saldo | Generate trial balance dan validasi keseimbangan debet-kredit. |
| **8. Financial Statements** | Laporan Keuangan | Generate Neraca, Laba Rugi, Arus Kas, Perubahan Ekuitas. |
| **9. Period Lock** | Kunci Periode | Mengunci periode agar tidak ada transaksi baru yang dapat diposting. |
| **10. Post-Closing** | Jurnal Penutup | Menutup akun nominal (revenue & expense) ke retained earnings. |

### 9. 📊 Laporan Keuangan (Financial Report)
#### 9.1 Neraca (Balance Sheet)
| Komponen | Penjabaran |
|---|---|
| **Aset Lancar** | Kas, Bank, Piutang Usaha, Persediaan, Biaya Dibayar Dimuka. |
| **Aset Tidak Lancar** | Aset Tetap, Akumulasi Penyusutan, Aset Tak Berwujud, Investasi Jangka Panjang. |
| **Liabilitas Jangka Pendek** | Hutang Usaha, Hutang Bank Jangka Pendek, Biaya yang Masih Harus Dibayar. |
| **Liabilitas Jangka Panjang** | Hutang Bank Jangka Panjang, Hutang Obligasi, Liabilitas Pajak Tangguhan. |
| **Ekuitas** | Modal Saham, Agio Saham, Saldo Laba, Cadangan, Laba Periode Berjalan. |
| **Comparative** | Perbandingan dengan periode sebelumnya (YoY, QoQ, MoM). |

#### 9.2 Laba Rugi (Income Statement)
| Komponen | Penjabaran |
|---|---|
| **Pendapatan** | Revenue dari penjualan barang/jasa, pendapatan lain-lain. |
| **HPP/COGS** | Harga Pokok Penjualan berdasarkan metode valuasi yang dipilih. |
| **Laba Kotor** | Revenue - COGS. |
| **Beban Operasional** | Beban penjualan, beban umum & administrasi. |
| **Laba Operasional** | Laba Kotor - Beban Operasional. |
| **Pendapatan/Beban Lain** | Pendapatan bunga, beban bunga, gain/loss kurs, gain/loss disposal asset. |
| **Laba Sebelum Pajak** | Laba Operasional + Pendapatan Lain - Beban Lain. |
| **Beban Pajak** | PPh Badan terutang. |
| **Laba Bersih** | Laba Sebelum Pajak - Beban Pajak. |

#### 9.3 Laporan Pendukung
| Laporan | Penjabaran |
|---|---|
| **Arus Kas** | Operating, Investing, Financing activities (langsung/tidak langsung). |
| **Perubahan Ekuitas** | Pergerakan modal, retained earnings, cadangan selama periode. |
| **Catatan Atas Laporan Keuangan** | Disclosure lengkap kebijakan akuntansi dan rincian pos-pos laporan keuangan. |
| **Segment Report** | Laporan kinerja per segmen bisnis/geografis. |
| **Management Report** | Laporan internal untuk manajemen dengan KPI dan analisis varians. |

---

## MJournal — Mesin Akuntansi Otomatis

> `MJournal` adalah **core engine** dari seluruh pencatatan Double-Entry Bookkeeping di dalam sistem. Alih-alih menulis logika jurnal di setiap controller transaksi, sistem memusatkannya di model ini secara **Subledger-to-General Ledger Automation**.

### 🔄 Sumber Transaksi Akuntansi Utama (`source`)
Metode `createJournal` dan `updateJournal` menerima parameter `source` untuk membedakan perlakuan debet/kredit secara otomatis:

| # | Source | Nama | Jurnal Otomatis |
|---|---|---|---|
| 1 | **GRPO** | Goods Receipt PO | Debit: Inventory Account <br> Kredit: Goods Clearing Account |
| 2 | **APINV** | A/P Invoice | Debit: Goods Clearing / Expense Account <br> Debit: VAT In Account <br> Kredit: A/P Account |
| 3 | **DO** | Delivery Order | Debit: COGS Account <br> Kredit: Inventory Account |
| 4 | **ARINV** | A/R Invoice | Debit: A/R Account <br> Kredit: Revenue Account <br> Kredit: VAT Out Account |
| 5 | **OUTPAY** | Outgoing Payment | Debit: A/P Account <br> Debit: Purchase Discount (jika ada) <br> Kredit: Cash/Bank Account |
| 6 | **INPAY** | Incoming Payment | Debit: Cash/Bank Account <br> Debit: Sales Discount (jika ada) <br> Kredit: A/R Account |
| 7 | **IP** | Issue for Production | Debit: WIP Inventory Account <br> Kredit: Raw Material Inventory Account |
| 8 | **RP** | Receipt from Production | Debit: Finished Goods Inventory Account <br> Kredit: WIP Offset/Clearing Account |
| 9 | **LOADING** | Loading & Ekspedisi | Debit: Shipped Goods / COGS Account <br> Kredit: Staging Inventory Account |
| 10 | **STOCK OPNAME** | Stock Opname | Debit/Kredit: Inventory Account <br> Kredit/Debit: G/L Increase (Gain) atau G/L Decrease (Loss) Account |
| 11 | **ARCM** | AR Credit Memo | **Debit:** Sales Return Account<br>**Debit:** VAT Out Account<br>**Kredit:** A/R Account<br>*(Disertai jurnal balik inventori: Debit Inventory, Kredit COGS)* |
| 12 | **APCM** | AP Credit Memo | **Debit:** A/P Account<br>**Kredit:** Inventory / Goods Clearing Account<br>**Kredit:** VAT In Account |
| 13 | **LANDED COST** | Biaya Pengiriman | **Debit:** Inventory Account<br>**Kredit:** Freight Clearing / Expense Clear Account |
| 14 | **WASTE** | Waste | **Debit:** Production Variance / Scrap Expense<br>**Kredit:** WIP Inventory Account |
| 15 | **VARIANCE ADJUSTMENT** | Work Order Close Variance | **Debit/Kredit:** WIP Inventory Variance Account<br>**Kredit/Debit:** WIP Inventory Account |
| 16 | **DEPRECIATION** | Penyusutan Aset Tetap | **Debit:** Depreciation Expense Account<br>**Kredit:** Accumulated Depreciation Account |
| 17 | **ARDP** | AR Down Payment | **Customer DP:** Debit Cash/Bank, Kredit Customer Deposit |
| 18 | **APDP** | AP Down Payment) | **Vendor DP:** Debit Vendor Advance, Kredit Cash/Bank |
| 19 | **EX REVALUATION** | Revaluasi Kurs | **Debit/Kredit:** Unrealized Gain/Loss Account<br>**Kredit/Debit:** A/R / A/P Account |

### 🗺️ Mekanisme Pemetaan Akun Dinamis (Dynamic COA Mapping)
`MJournal` membaca hierarki akun secara dinamis **tanpa hard-code**, melalui alur relasi:
> **Transaksi Detail** → **Item Master** → **Item Group** → **Parameter Akun (26 COA)**

**Keunggulan:**
- ✅ Setiap kategori produk (Bahan Baku, Barang Setengah Jadi, Barang Jadi, Jasa) masuk ke pos neraca atau laba rugi yang tepat secara otomatis.
- ✅ Perubahan akun cukup dilakukan di level Item Group, tidak perlu ubah code.
- ✅ Konsistensi jurnal across seluruh transaksi.

### ↩️ Auto-Reversal & Update Jurnal
Jika terjadi pembatalan (`savecancel`) atau revisi (`saveedit`) pada dokumen referensi:

| Aksi | Efek |
|---|---|
| **Pembatalan**  | Membalikkan jurnal lama (reverse entry) |
| **Revisi**  | Menghapus jurnal lama, generate jurnal baru |
| **Hasil**  | Neraca saldo (trial balance) tetap akurat tanpa data yatim (orphan data) |

---

## 26 Pemetaan Akun (COA Mapping) pada Item Group

Berdasarkan form **"Tambah Item Grup"** dengan tab **Account**, berikut adalah 26 parameter akun yang harus dikonfigurasi untuk memastikan laporan keuangan akurat:

### 🏷️ A. Siklus Penjualan (Sales & Revenue) — 6 Akun
| # | Nama Akun | Fungsi | Dipicu Oleh |
|---|---|---|---|
| 1 | **REVENUE ACCOUNT** | Akun pendapatan utama yang dikredit saat penjualan diakui. | A/R Invoice |
| 2 | **COST OF GOODS SOLD ACCOUNT** | Akun HPP yang didebet saat barang keluar dari gudang. | Delivery Order (DO) |
| 3 | **SALES RETURN ACCOUNT** | Akun pengurang pendapatan apabila pelanggan meretur barang. | Sales Return / Credit Note |
| 4 | **SALES CREDIT ACCOUNT** | Akun potongan harga/diskon penjualan khusus di luar potongan baris. | Sales Discount / Credit Memo |
| 5 | **SHIPPED GOODS ACCOUNT** | Akun transit persediaan ketika barang sudah dikirim namun belum difaktur. | Loading / Delivery (sebelum invoicing) |
| 6 | **VAT OUT ACCOUNT** | Akun PPN Keluaran yang dikredit saat invoice diterbitkan ke pelanggan. | A/R Invoice |

### 🛒 B. Siklus Pembelian & Persediaan (Purchasing & Inventory) — 6 Akun
| # | Nama Akun | Fungsi | Dipicu Oleh |
|---|---|---|---|
| 7 | **INVENTORY ACCOUNT** | Akun aset persediaan riil di Neraca (didebet saat GRPO, dikredit saat DO/Issue). | GRPO, DO, Issue Production |
| 8 | **EXPENSE ACCOUNT** | Akun beban langsung untuk pembelian barang non-inventori atau jasa. | A/P Invoice (non-inventory) |
| 9 | **GOODS CLEARING ACCOUNT** | Akun penyeimbang (GRNI) saat GRPO sebelum tagihan vendor diterima. | GRPO |
| 10 | **PURCHASE CREDIT ACCOUNT** | Akun potongan atau memo kredit dari Supplier. | Purchase Return / Debit Memo |
| 11 | **PRICE DIFFERENCE ACCOUNT** | Menampung selisih harga antara saat GRPO dengan saat A/P Invoice. | APINV (jika harga berbeda dengan GRPO) |
| 12 | **VAT IN ACCOUNT** | Akun PPN Masukan yang didebet saat menerima tagihan/pembelian berpajak. | GRPO, A/P Invoice |

### 🚚 C. Penyesuaian & Transit Logistik — 7 Akun
| # | Nama Akun | Fungsi | Dipicu Oleh |
|---|---|---|---|
| 13 | **STOCK IN TRANSIT ACCOUNT** | Menampung nilai barang dalam proses mutasi antar gudang atau ekspedisi. | Inter-warehouse Transfer, Loading |
| 14 | **G/L INCREASE ACCOUNT** | Akun pendapatan/keuntungan (Gain) hasil surplus Stock Opname. | Stock Opname (selisih lebih) |
| 15 | **G/L DECREASE ACCOUNT** | Akun beban/kerugian (Loss) hasil defisit/hilang Stock Opname. | Stock Opname (selisih kurang) |
| 16 | **INVENTORY OFFSET INCREASE ACCOUNT** | Akun perantara untuk penyesuaian kenaikan stok manual. | Manual Stock Adjustment (increase) |
| 17 | **INVENTORY OFFSET DECREASE ACCOUNT** | Akun perantara untuk penyesuaian penurunan stok manual. | Manual Stock Adjustment (decrease) |
| 18 | **NEGATIVE INVENTORY ACCOUNT** | Akun penyeimbang apabila sistem mengizinkan transaksi minus stok sementara. | Transaksi dengan stok negatif |
| 19 | **INVENTORY OFFSET ACCOUNT** | Akun perantara untuk saldo awal persediaan (Opening Balance). | Opening Balance Inventory |
| 20 | **EXPENSE CLEAR ACCOUNT** | Akun clearing untuk beban operasional yang belum ditagih. | Accrued Expense |

### 🏭 D. Siklus Pabrikasi (Manufacturing) — 3 Akun
| # | Nama Akun | Fungsi | Dipicu Oleh |
|---|---|---|---|
| 21 | **WIP INVENTORY ACCOUNT** | Akun Barang Dalam Proses (didebet saat bahan baku ditarik ke produksi). | Issue for Production (IP) |
| 22 | **WIP OFFSET ACCOUNT** | Akun lawan/clearing saat material masuk lini produksi. | Issue for Production (IP) |
| 23 | **WIP INVENTORY VARIANCE ACCOUNT** | Menampung selisih biaya material standar vs aktual saat Work Order ditutup. | Receipt from Production (RP) — closing WO |

### ⚙️ E. Akun Sistem, Alokasi, & Selisih Kurs — 4 Akun
| # | Nama Akun | Fungsi | Dipicu Oleh |
|---|---|---|---|
| 24 | **EXCHANGE RATE ACCOUNT** | Menampung laba/rugi selisih kurs mata uang asing (Realized/Unrealized Gain & Loss). | FX Revaluation, Payment dalam foreign currency |
| 25 | **ALLOCATION ACCOUNT** | Menampung biaya tambahan (Landed Cost seperti ongkir/bea masuk) yang dialokasikan ke nilai persediaan. | Landed Cost Allocation |
| 26 | **VARIANCE ACCOUNT** | Akun selisih umum akibat metode valuasi rata-rata bergerak (Moving Average). | Inventory Valuation Adjustment |

---

## Manajemen Pajak (Tax Management)

Logika perhitungan pajak disematkan secara mulus di berbagai modul transaksi (SO, PO, SQ) yang berkomunikasi dengan modul **MTax**.

### 🧮 Variabel Kalkulasi (Subtotal, DPP, & Tax)
Pada setiap Controller transaksi, sistem mengeksekusi `calculateHeaderTotals()` berdasarkan parameter `taxclude`:

| Parameter | Nama | Rumus Perhitungan | Contoh |
|---|---|---|---|
| `taxclude = 0` | **Exclude** | DPP = Subtotal - Diskon <br> PPN = DPP × Rate | Subtotal Rp 1.000.000, Diskon 10% <br> DPP = Rp 900.000 <br> PPN 11% = Rp 99.000 <br> **Grand Total = Rp 999.000** |
| `taxclude = 1` | **Include** | DPP = Grand Total / (1 + Rate Pajak) <br> PPN = Grand Total - DPP | Grand Total Rp 1.110.000 <br> DPP = 1.110.000 / 1.11 = Rp 1.000.000 <br> PPN = Rp 110.000 |

### 📑 Jurnal Pajak (VAT / PPN)
| Jenis Pajak | Kapan Dipicu | Jurnal |
|---|---|---|
| **VAT In (Pajak Masukan)** | Saat GRPO atau A/P Invoice | Debit: VAT In Account <br> Kredit: A/P Account (bagian pajak) |
| **VAT Out (Pajak Keluaran)** | Saat A/R Invoice | Debit: A/R Account (bagian pajak) <br> Kredit: VAT Out Account |

### 🛡️ Perlindungan Margin, Valuasi, & Produksi
| Kontrol | Penjabaran |
|---|---|
| **Price Validation** | Harga jual divalidasi ke master `Pricelist` yang aktif berdasarkan tanggal efektif. |
| **Discount Validation** | Diskon baris divalidasi batas atasnya melawan `Discountlist`. |
| **Margin Protection** | Sistem menolak otomatis jika harga jual di bawah standar atau diskon melebihi batas maksimal persetujuan. |
| **Multi-currency** | Support transaksi dalam berbagai mata uang. |
| **Exchange Rate Management** | Pengelolaan nilai tukar dengan sistem **anti-collision** (mencegah double entry rate untuk tanggal yang sama). |
| **Auto Conversion** | Konversi otomatis ke mata uang laporan (functional currency). |
| **Costing & Valuasi Produksi** | IP (Debit WIP, Kredit Raw Material), RP (Debit Finished Goods, Kredit WIP Clearing), DO/Loading (Debit COGS, Kredit Inventory). |

---

## Integrasi Modul Transaksi & Peran Kerja Tim FAT

### 🏷️ a. Pengendalian Harga & Diskon Penjualan (`MSalesquotation` & `MPricelist`)
| Peran FAT | Aktivitas |
|---|---|
| **Margin Control** | Mengawasi kepatuhan margin penjualan. |
| **Price Validation** | Sistem melalui `MPricelist` dan `MDiscountlist` menolak otomatis jika harga jual di bawah standar. |
| **Discount Control** | Diskon melebihi batas maksimal memerlukan approval berjenjang. |
| **Tax Validation** | Menggunakan parameter `taxclude` (0 untuk Exclude, 1 untuk Include) untuk memisahkan DPP dan PPN secara presisi. |

### 📥 b. Manajemen Piutang & Penagihan (`MArinvoice` & `MIncomingpayment`)
| Modul | Fungsi |
|---|---|
| **A/R Invoice (`MArinvoice`)** | Mengakui tagihan ke pelanggan berdasarkan referensi Delivery Order (`MDeliveryorder`) atau Packing (`MPacking`). |
| **Incoming Payment (`MIncomingpayment`)** | Mengelola alokasi pelunasan piutang ke nomor dokumen faktur terkait (`amountapplied`). Mengurangi saldo terutang secara real-time dan menjurnal penerimaan kas/bank. |

### 📋 c. Manajemen Hutang & Pembelian
| Modul | Fungsi |
|---|---|
| **`MPurchaserequest`** | Permintaan pembelian oleh user department. |
| **`MPurchaseorder`** | Pemesanan resmi ke supplier. |
| **`MGrpo`** | Penerimaan fisik barang, memicu Goods Clearing. |
| **`MApinvoice`** | Accounting mencocokkan tagihan vendor dengan GRPO, memproses PPN Masukan, menjurnal hutang dagang. |
| **`MOutgoingpayment`** | Pembayaran ke vendor berdasarkan Balance Due, memperbarui status `paidamount`, menutup kewajiban. |

### 📦 d. Logistik, Staging, & Distribusi (`MPacking` & `MLoading`)
| Modul | Fungsi |
|---|---|
| **Packing (`MPacking`)** | Memindahkan inventori fisik dari gudang utama ke gudang staging persiapan pengiriman (`STP`) **tanpa menghapus nilai aset** secara akuntansi. |
| **Loading (`MLoading`)** | Memvalidasi manifest pengiriman, nomor plat kendaraan, dan jenis ekspedisi sebelum memicu pencatatan HPP dan perpindahan ke status barang terkirim. |

### 🏭 e. Akuntansi Manufaktur & Biaya Produksi (`MIssueproduction` & `MReceiptproduction`)
| Modul | Fungsi |
|---|---|
| **Issue for Production (`MIssueproduction`)** | Menjurnal perpindahan material dari gudang bahan baku ke akun WIP. |
| **Receipt from Production (`MReceiptproduction`)** | Menerima barang jadi dari pabrik, melakukan generate nomor Batch otomatis, dan menutup Work Order dengan memperhitungkan toleransi produksi. |

---

## Output & Teknologi

| Aspek | Detail |
|---|---|
| **Platform** | Web-based ERP dengan API-first architecture |
| **Database** | Relational database (PostgreSQL/MySQL) dengan ACID compliance untuk integritas transaksi keuangan |
| **Double-Entry Engine** | `MJournal` sebagai core engine dengan auto-reversal dan dynamic COA mapping |
| **Real-time Processing** | Update saldo dan laporan keuangan secara real-time |
| **Multi-currency** | Support transaksi multi-mata uang dengan auto FX revaluation |
| **Multi-entity** | Support konsolidasi laporan keuangan untuk grup perusahaan |
| **Audit Trail** | Jejak audit lengkap untuk setiap transaksi keuangan |
| **Security** | Role-based access control, encryption at rest & in transit, segregation of duties |
| **Compliance** | PSAK, IFRS, regulasi perpajakan Indonesia (PPN, PPh) |
| **Integration** | REST API untuk integrasi dengan modul Sales, Purchasing, Inventory, Production, HR |
| **Reporting** | Financial report generator dengan drill-down capability |
| **Backup & Recovery** | Automated backup dengan point-in-time recovery untuk data keuangan |

---

## 📌 Catatan Penting untuk Tim FAT

1. **Konfigurasi Item Group adalah Kunci** — Keakuratan 26 akun di Item Group menentukan kebenaran seluruh jurnal otomatis. Review berkala wajib dilakukan.
2. **Period Closing Harus Tertib** — Tutup buku periode hanya dilakukan setelah semua transaksi tercatat dan trial balance balance.
3. **Rekonsiliasi Rutin** — Lakukan rekonsiliasi bank, piutang, hutang, dan persediaan minimal bulanan.
4. **Backup Data Keuangan** — Data keuangan adalah data kritis. Pastikan backup dilakukan secara reguler dengan prosedur disaster recovery yang teruji.
5. **Segregation of Duties** — Pemisahan tugas antara pembuat transaksi, approver, dan pencatat jurnal wajib diterapkan untuk mencegah fraud.

---

> 📌 **Catatan:** Dokumentasi ini bersifat *living document* dan akan diperbarui seiring perkembangan requirement dan implementasi sistem.

---
*Dokumen ini dibuat berdasarkan spesifikasi modul SH-FIN dan SH-ACC untuk sistem ERP Enterprise terintegrasi.*