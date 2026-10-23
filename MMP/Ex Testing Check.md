Apalagi Odoo 19 Community menggunakan struktur yang berbasis open-source, sehingga semua kode kustomisasi dari vendor (baik di sisi Python/backend maupun Owl Framework JavaScript/frontend) dapat diinspeksi secara transparan.
Berikut adalah panduan teknis bagi Anda sebagai sesama programmer untuk memeriksa kualitas kode kustom dari vendor:

1. File Apa Saja yang Wajib Anda Periksa?

Di dalam struktur modul kustom Odoo, fokuskan audit Anda pada file-file krusial berikut:
• static/src/js/ atau static/src/components/: Di sinilah letak logika komponen Owl Framework (Odoo 19) dan skrip Service Worker PWA. Periksa bagaimana mereka menangani penyimpanan lokal (IndexedDB atau LocalStorage) saat jaringan terputus.
• manifest.json (Aset PWA): Cari file manifest PWA di dalam folder static. Pastikan rute ikon, warna tema perusahaan, nama aplikasi, dan start_url sudah mengarah dengan benar dan tidak bersifat hardcoded ke server uji milik vendor.
• controllers/main.py atau rute API: PWA Odoo berkomunikasi dengan backend melalui HTTP requests atau JSON-RPC. Cek file Python ini untuk memastikan rute yang dibuat vendor dilindungi oleh otentikasi yang tepat (auth='user') agar data kantor tidak bocor ke publik.

2. Cara Mengecek Kode secara Otomatis (Static Analysis)

Sebagai programmer, jangan mengecek ribuan baris kode secara manual. Gunakan alat bantu (linter/scanner) berikut:
• Gunakan ESLint & Prettier ****uk Frontend JS/Owl)): Jalankan ESLint pada folder static/src modul vendor. Ini penting untuk memastikan kode JavaScript mereka rapi, tidak ada memory leak pada Service Worker, dan sesuai dengan coding style standar Odoo.
• Gunakan Bandit ****uk Backend Python): Bandit adalah alat security scanner untuk Python. Jalankan perintah bandit -r <nama_modul_vendor> lewat terminal Anda. Alat ini akan otomatis mendeteksi jika vendor tidak sengaja menulis celah keamanan seperti SQL Injection pada kueri Odoo (cr.execute) atau pembocoran data.

3. "Red Flags" (Lampu Merah) yang Harus Anda Waspadai dalam Kode Vendor

Saat Anda membaca kode mereka, segera tegur vendor jika Anda menemukan praktik buruk (bad practices) berikut:
• Hardcoded API Keys atau Hardcoded URL: Jika Anda menemukan URL server internal vendor atau password ditulis langsung di dalam kode, minta mereka memindahkannya ke sistem konfigurasi Odoo (ir.config_parameter).
• Sinkronisasi Offline yang Monolitik: Periksa apakah saat kembali online, PWA mengirimkan seluruh data dari awal (tidak efisien dan membuat server jebol). Kode yang baik harusnya hanya mengirimkan perubahan data saja (delta sync).
• Tidak Ada Error Handling pada Service Worker: Jika kode fetch listener di Service Worker mereka tidak memiliki blok catch() atau penanganan ketika server Odoo sedang down, PWA akan langsung crash atau menampilkan halaman putih kosong di HP karyawan Anda.

===

Di Odoo 19 Community, modifikasi masif pada modul bawaan (base modules) memerlukan perhatian ekstra agar sistem tidak lambat dan tetap bisa di-upgrade di masa depan.
Untuk menghadapi kustomisasi yang sangat banyak ini, Anda perlu membagi strategi pengecekan kode (code review) ke dalam tiga lapisan arsitektur Odoo 19:

1. Lapisan Backend (Python & Odoo ORM)

Ketika vendor menulis banyak logika bisnis baru, fokuslah pada efisiensi kueri database dan struktur modularitasnya:
• Hindari "N+1 Query Problem" (Looping ORM): Periksa apakah vendor menggunakan perulangan for record in records: lalu di dalamnya melakukan pencarian database lagi (seperti .search() atau membaca relational field). Ini akan membuat ERP sangat lambat saat data kantor sudah ribuan. Vendor harus menggunakan teknik eager loading (seperti .read() atau memetakan data dengan dictionary).
• Inheritance (Pewarisan) yang Bersih: Pastikan vendor menggunakan _inherit dengan benar tanpa merusak fungsi bawaan Odoo. Pastikan di setiap fungsi yang mereka timpa (override), terdapat baris super(NamaKelas, self).nama_fungsi(*args, **kwargs) agar fungsi standar Odoo tetap berjalan di latar belakang.
• Penggunaan Database Indexing: Untuk bidang kustom (custom fields) yang sering dicari, difilter, atau diurutkan oleh karyawan Anda (misalnya nomor dokumen kustom, status khusus), pastikan vendor menambahkan properti index=True pada definisi field Python-nya.

2. Lapisan Frontend (Owl Framework & Javascript PWA)

Odoo 19 sangat bergantung pada Owl Framework (komponen berbasis JavaScript yang mirip dengan Vue/React). Karena ini terintegrasi dengan PWA, cek poin-poin berikut:
• Manajemen Status Patah Jaringan (Network State): Cari tahu bagaimana kode frontend mendeteksi status internet. Kode harus memanfaatkan event listener window.addEventListener('offline', ...) untuk langsung mengubah UI menjadi mode terbatas secara anggun (graceful degradation), bukan membiarkan halaman error loading bawaan browser muncul.
• Penyimpanan Data Lokal Skala Besar: Karena kustomisasi Anda banyak, volume data yang disinkronkan ke HP pasti besar. Pastikan vendor menggunakan IndexedDB melalui pustaka seperti Dexie.js atau localForage untuk menyimpan data offline, bukan LocalStorage (karena LocalStorage memiliki batas kapasitas yang sangat kecil, biasanya hanya 5MB).
• Siklus Hidup Komponen Owl (Lifecycle Hooks): Pastikan vendor membersihkan data atau event listener pada fungsi willUnmount() di komponen Owl mereka untuk mencegah kebocoran memori (memory leaks) yang bisa membuat browser HP karyawan freeze setelah pemakaian lama.

3. Lapisan Database & Migrasi

• File Data XML yang Bersih: Cek semua file di dalam folder views/. Pastikan vendor menggunakan atribut position="after", "before", atau "attributes" saat memodifikasi tampilan bawaan, bukan mengganti seluruh arsitektur view asli secara total (position="replace" harus diminimalisir agar aman saat pembaruan sistem).
• Skrip Migrasi Data: Karena kustomisasinya masif, tanyakan bagaimana vendor menangani migrasi data dari sistem lama ke modul baru ini. Apakah mereka menyiapkan skrip instalasi awal (data.xml) atau menggunakan skrip Python migration?

Strategi Alur Kerja Audit Anda (Agar Tidak Kewalahan)

Dengan volume kustomisasi yang besar, mereview baris demi baris secara manual akan memakan waktu berbulan-bulan. Terapkan alur kerja ini:
[Vendor Push Code] ──> [Jalankan Automated Linting (Bandit/ESLint)] ──> [Cek Modul Kritis Saja Secara Manual] ──> [Uji Beban di UAT]
1. Minta Struktur Modul yang Terpisah: Tegaskan kepada vendor agar setiap kustomisasi besar dipisahkan menjadi modul Odoo tersendiri (misal: custom_hr, custom_inventory, custom_pwa_core), jangan ditumpuk dalam satu modul raksasa (monolithic module). Ini akan memudahkan Anda mengisolasi kode saat mencari bug.
2. Gunakan Fitur Git Diff: Setiap kali vendor menyerahkan pembaruan, jangan cek dari nol. Cukup gunakan perintah git diff untuk melihat baris kode mana saja yang baru ditambahkan atau diubah sejak versi pemeriksaan Anda yang terakhir.
