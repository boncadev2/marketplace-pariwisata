<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ArticleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => 'Panduan Lengkap Jelajah Pantai Berpasir Putih & Bebatuan Granit Eksotis',
                'slug' => 'panduan-lengkap-jelajah-pantai-pasir-putih-granit',
                'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Panduan Wisata',
                'author_name' => 'Tim Redaksi Wisata',
                'excerpt' => 'Temukan rute terbaik, waktu paling ideal menikmati matahari terbenam, serta rekomendasi spot foto spektakuler di pesisir bebatuan granit purba.',
                'body' => "## Pesona Alam Pesisir Nusantara\n\nMenjelajahi kawasan pesisir dengan gugusan batu granit raksasa dan hamparan pasir putih lembut selalu menyajikan pengalaman tak terlupakan bagi setiap pelancong. Suasana ombak yang tenang dipadukan dengan semilir angin tropis menjadikannya tempat pelarian sempurna dari hiruk-pikuk perkotaan.\n\n### Waktu Terbaik Berkunjung\n\nUntuk mendapatkan pencahayaan foto terbaik dan terhindar dari terik matahari yang menyengat, sangat disarankan tiba di lokasi pada pagi hari sebelum pukul 09.00 WIB atau sore hari mulai pukul 16.00 WIB untuk menyaksikan panorama matahari tenggelam (sunset).\n\n### Perlengkapan yang Perlu Dibawa\n\n1. **Kacamata Hitam & Tabir Surya (Sunscreen)** untuk melindungi kulit dari sinar UV.\n2. **Kamera atau Ponsel dengan Baterai Penuh** karena setiap sudut menyajikan latar belakang foto memukau.\n3. **Pakaian Ganti & Sandal Tahan Air** jika berencana bermain air di tepi pantai.\n4. **Kantong Sampah Ramah Lingkungan** guna menjaga kebersihan alam destinasi wisata kita.\n\nMari selalu dukung pariwisata berkelanjutan dengan menghargai kearifan lokal dan menjaga kelestarian lingkungan pesisir.",
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(5),
                'meta_title' => 'Panduan Wisata Pantai Pasir Putih & Bebatuan Granit',
                'meta_description' => 'Tips dan panduan menjelajahi pesona pantai tropis berpasir putih dan batuan granit eksotis di nusantara.',
            ],
            [
                'title' => '5 Kuliner Otentik Khas Daerah yang Wajib Dicoba Saat Berlibur',
                'slug' => '5-kuliner-otentik-khas-daerah-wajib-dicoba',
                'image_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Kuliner Lokal',
                'author_name' => 'Dewi Savitri',
                'excerpt' => 'Eksplorasi cita rasa rempah nusantara mulai dari olahan hidangan laut segar, sup rempah berkuah hangat, hingga camilan manis khas UMKM desa.',
                'body' => "## Menyusuri Kekayaan Rasa Lokal\n\nLiburan belum lengkap tanpa menyelami kuliner lokal yang diwariskan turun-temurun. Setiap daerah menyimpan rahasia bumbu khas yang mencerminkan kekayaan rempah nusantara.\n\n### Daftar Kuliner Rekomendasi\n\n1. **Gangan Ikan Kuah Kuning**: Perpaduan asam segar buah nanas dan kuah kunyit hangat berpadu serasi dengan ikan laut tangkapan nelayan lokal.\n2. **Mie Kuah Ikan Khas Pesisir**: Kuah kaldu ikan yang gurih kental berpadu dengan mie kenyal bertabur tauge dan daun seledri.\n3. **Kopi Manggar & Camilan Tradisional**: Menyeruput kopi saring tradisional ditemani gorengan pisang keju hangat di warung kopi bersejarah.\n4. **Kue Semprong & Kerupuk Ikan Kemplang**: Oleh-oleh renyah buatan ibu-ibu pengrajin UMKM binaan desa wisata.\n\nJangan ragu untuk mampir ke sentra kuliner UMKM lokal yang terdaftar di aplikasi untuk mencicipi rasa aslinya langsung dari tangan para juru masak tradisional.",
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(3),
                'meta_title' => '5 Kuliner Otentik Khas Daerah Wajib Dicoba',
                'meta_description' => 'Eksplorasi kuliner tradisional daerah, dari hidangan laut kuah kuning hingga jajanan pasar UMKM.',
            ],
            [
                'title' => 'Tips Liburan Keluarga Hemat & Nyaman ke Desa Wisata',
                'slug' => 'tips-liburan-keluarga-hemat-dan-nyaman-ke-desa-wisata',
                'image_url' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Tips Liburan',
                'author_name' => 'Bambang Prasetyo',
                'excerpt' => 'Rencanakan liburan bersama buah hati dan keluarga besar dengan bujet terjangkau namun tetap mengutamakan kenyamanan dan edukasi budaya.',
                'body' => "## Liburan Bermakna Bersama Keluarga\n\nDesa wisata kini menjadi primadona destinasi ramah keluarga. Selain menyuguhkan pemandangan asri, anak-anak dapat belajar bercocok tanam, membatik, hingga mengenal satwa endemik secara langsung.\n\n### Kiat Cerdas Mengatur Liburan\n\n* **Pesan Tiket & Paket Wisata Lebih Awal**: Manfaatkan kupon promo platform dan pemesanan paket bundling rombongan untuk mendapatkan potongan harga spesial.\n* **Pilih Homestay Berlisensi Resmi**: Menginap di homestay milik warga desa memberikan pengalaman interaksi hangat serta menghemat biaya akomodasi hingga 50% dibanding hotel berbintang.\n* **Lengkapi Data Manifest Rombongan**: Pastikan nomor kontak darurat dan identitas anggota keluarga tercatat saat pemesanan untuk perlindungan asuransi wisata terpadu.\n\nDengan perencanaan matang, momen liburan keluarga menjadi penuh kenangan indah tanpa menguras kantong.",
                'status' => 'published',
                'published_at' => Carbon::now()->subDays(1),
                'meta_title' => 'Tips Liburan Keluarga Hemat ke Desa Wisata',
                'meta_description' => 'Strategi cerdas merencanakan liburan keluarga hemat, aman, dan berkesan di desa wisata.',
            ],
            [
                'title' => 'Menyelami Kearifan Lokal & Budaya Kerajinan Anyaman Bambu Desa',
                'slug' => 'menyelami-kearifan-lokal-kerajinan-anyaman-bambu',
                'image_url' => 'https://images.unsplash.com/photo-1544644181-1484b3fdfc62?auto=format&fit=crop&w=1200&q=80',
                'category' => 'Tradisi & Budaya',
                'author_name' => 'Siti Nurhaliza',
                'excerpt' => 'Kisah para perajin senior menjaga warisan anyaman bambu bernilai seni tinggi yang kini menembus pasar cenderamata mancanegara.',
                'body' => "## Warisan Leluhur yang Tetap Bertahan\n\nDi balik keteduhan pepohonan bambu pedesaan, tangan-tangan terampil para sesepuh desa dengan telaten menyusun bilah-bilah bambu menjadi berbagai perabot dan cenderamata artistik.\n\n### Proses Pembuatan yang Penuh Ketelitian\n\nSetiap batang bambu dipilih secara khusus saat memasuki usia matang, kemudian dikeringkan secara alami agar tahan terhadap cuaca dan rayap. Proses pengayatan membutuhkan keterampilan tinggi agar serat tetap halus dan nyaman saat disentuh.\n\nSaat berkunjung, wisatawan dapat mengikuti workshop singkat dan membawa pulang hasil anyaman karya sendiri sebagai oleh-oleh istimewa yang memiliki nilai kenangan tinggi.",
                'status' => 'published',
                'published_at' => Carbon::now(),
                'meta_title' => 'Kerajinan Anyaman Bambu Tradisional Desa Wisata',
                'meta_description' => 'Mengenal warisan seni anyaman bambu tradisional dan workshop kerajinan tangan di desa wisata.',
            ],
        ];

        foreach ($articles as $data) {
            Article::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
