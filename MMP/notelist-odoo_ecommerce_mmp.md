Mengenai Kesenjangan Arsitektur (Microservice vs Modular Monolith):
1. Mengingat requirement kami mewajibkan arsitektur microservice, jika layanan di luar Odoo dibangun terpisah oleh MMP (seperti yang disebutkan di jawaban No. 10), tech stack apa yang akan MMP gunakan untuk layanan terpisah tersebut? Apakah tetap menggunakan ekosistem Odoo/Python atau di luar itu (misalnya PHP/Node.js)?  
2. Bagaimana MMP menangani konsistensi data dan manajemen transaksi lintas layanan (distributed transaction/two-phase commit) antara aplikasi microservice terpisah tersebut dengan core Odoo jika terjadi kegagalan jaringan di tengah proses?

Mengenai Performa dan Skala Besar (Mengingat target 400 user++):
3. Bagaimana strategi MMP dalam mengelola concurrency dan load balancing jika 400+ pengguna mengakses sistem secara bersamaan, mengingat Odoo secara bawaan menggunakan arsitektur berbasis process/worker (Gunicorn/Multiprocessing)? Apakah MMP akan menyiapkan konfigurasi database replication (Read/Write splitting)?
4. Terkait database ganda (multiple database untuk operasional dan pajak) yang diwajibkan, bagaimana mekanisme sinkronisasi atau pemisahan data otomatis yang akan diterapkan oleh MMP pada tingkat ORM atau controller Odoo?

Mengenai Kustomisasi, Pemeliharaan, dan Upgrade:
5. Karena major upgrade Odoo berisiko merusak endpoint atau payload API kustom (seperti yang MMP akui di jawaban No. 5), bagaimana komitmen SLA dari MMP dalam menjamin migrasi kustomisasi tersebut, dan apakah biaya refactoring tersebut sudah termasuk dalam garansi pemeliharaan atau dihitung sebagai project baru?
6. Apakah MMP menerapkan praktik CI/CD (Continuous Integration/Continuous Deployment) otomatis untuk deployment modul kustom dan pembaruan API, serta bagaimana versioning API dikelola agar tidak memutus integrasi dengan sistem eksternal?

Mengenai Dokumentasi dan Standar Pengembangan:
7. Mengingat tidak ada dokumentasi API otomatis seperti Swagger secara bawaan (jawaban No. 3), tools apa yang akan disediakan oleh MMP untuk mendokumentasikan seluruh custom endpoint yang dibuat agar tim internal PT LKPI dapat melakukan audit atau pengembangan mandiri di kemudian hari?
8. Bagaimana standar unit testing dan integration testing yang diterapkan oleh MMP pada setiap pembuatan custom API atau modul tambahan untuk memastikan kode bebas dari bug sebelum masuk tahap Go-Live?

Mengenai Keamanan dan Autentikasi Lanjutan:
9. Meskipun Odoo mendukung API Key dan sesi (jawaban No. 4), apakah MMP dapat menerapkan standar token JWT (JSON Web Token) dengan mekanisme token expiration dan refresh token untuk mengamankan komunikasi data ke aplikasi eksternal (seperti GPS atau e-Faktur)?
10. Bagaimana mekanisme pembatasan akses (rate limiting dan IP whitelisting) pada custom API yang dibangun oleh MMP guna mencegah potensi serangan siber atau DDoS dari luar?

Mengenai Integrasi E-Commerce dengan Multi-Entitas dan Ritel (Apotek & Distributor):
1.Bagaimana e-commerce Odoo menangani pemisahan inventory secara real-time untuk grup perusahaan yang kompleks (seperti PT LKPI, CV Tiga Likuid Plastindo, PT Trisa Likuid Farma, dan Apotek Likuid Farma)? 
2. Apakah satu keranjang belanja (cart) atau satu checkout dapat memetakan barang langsung dari gudang atau entitas yang berbeda secara otomatis?  Apakah e-commerce Odoo yang didemokan mendukung pengelolaan Multi-Price / Multi-Level Pricing (misalnya harga beda antara pembelian grosir untuk distributor PT Trisa, harga institusi rumah sakit, dan harga ritel untuk end-customer di Apotek)?  
3. Bagaimana modul e-commerce Odoo mengakomodasi alur diskon khusus yang kompleks seperti Discount Proposal Form (DPF) atau persetujuan berjenjang (approval workflow) diskon yang biasa digunakan oleh tim marketing PT LKPI? 

Mengenai Pengelolaan Katalog, Varian, dan Kepatuhan Industri (Alkes/Farmasi):
4. Apakah e-commerce Odoo mampu mengelola variasi produk yang spesifik (seperti ukuran, bahan metal/plastik, hingga manajemen nomor Batch dan Expired Date yang wajib untuk produk alat kesehatan dan farmasi) langsung pada halaman produk di frontend?
5. Bagaimana e-commerce Odoo mengelola produk yang statusnya out of stock atau pending product (produk kosong)? Apakah sistem menyediakan fitur backorder, notifikasi stok, atau pengalihan pemesanan otomatis ke gudang cabang terdekat?

Mengenai Pembayaran, Pajak, dan Integrasi POS/Retail:
6. Apakah sistem e-commerce Odoo sudah terintegrasi secara native dengan kanal pembayaran lokal di Indonesia (seperti QRIS, virtual account bank mayor, atau e-wallet), dan bagaimana mekanisme rekonsiliasi otomatisnya ke modul Finance & Accounting (SH-FIN > modul sharing divisi FAT) ?
7. Bagaimana e-commerce Odoo menangani pencatatan faktur pajak (e-Faktur) untuk transaksi digital, mengingat PT LKPI mensyaratkan adanya pemisahan database operasional dan database pajak?
8. Jika Apotek atau outlet ritel melakukan penjualan melalui POS (Point of Sales), apakah data penjualan POS tersebut tersinkronisasi secara langsung (real-time) dengan data penjualan di e-commerce, atau membutuhkan jeda waktu sinkronisasi (cron job)?

Mengenai Retur, Layanan Pelanggan (Customer Service), dan Pengiriman:
9. Bagaimana alur Retail Return atau Purchase Return (retur barang dari pelanggan/distributor) dikelola di dalam sistem e-commerce Odoo? Apakah proses persetujuan retur dan pemotongan saldo/nota kredit langsung terhubung ke modul Order Management (SH-OM > modul sharing Order Management)
10. Apakah modul e-commerce Odoo mendukung integrasi langsung dengan penyedia jasa ekspedisi pihak ketiga di Indonesia untuk pengecekan ongkos kirim secara otomatis dan pelacakan resi pengiriman (tracking), mengingat bagian Warehousing membutuhkan perencanaan pengiriman yang terstruktur?