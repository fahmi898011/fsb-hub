<?php

/**
 * Konfigurasi modul Chat (PRD Chat §8/§9).
 *
 * Batas panjang pesan sengaja TIDAK di-hard-code di controller/validasi,
 * melainkan dibaca dari sini supaya dapat diperbesar lewat `.env`
 * (mis. `CHAT_MESSAGE_MAX_LENGTH=5000`) tanpa mengubah kode.
 *
 * AGENT: asisten | 2026-10-06 | tambah edit_window_minutes + thread_preview_limit | modul Chat §15
 * AGENT: asisten | 2026-10-10 | perpendek interval poll + backoff dua tingkat | modul Chat §9
 */
return [

    // Panjang maksimum satu pesan (jumlah karakter).
    'message_max_length' => (int) env('CHAT_MESSAGE_MAX_LENGTH', 2000),

    // Ukuran maksimum SATU lampiran, dalam kilobyte (25 MB).
    'attachment_max_kb' => (int) env('CHAT_ATTACHMENT_MAX_KB', 25600),

    // Ekstensi lampiran yang diizinkan: gambar, PDF, dan dokumen Office.
    'attachment_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv',
    ],

    // Lampiran/gambar dibatasi agar tidak dijadikan tempat penyimpanan berkas
    // besar; berkas besar sebaiknya masuk Pusat Informasi (PRD Chat §8).
    'attachment_mime_types' => [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
    ],

    // Kuota kirim pesan per menit per pengguna (PRD §8).
    'send_rate_limit' => (int) env('CHAT_SEND_RATE_LIMIT', 60),

    /*
     * Batas waktu menyunting pesan, dalam menit (PRD §15).
     *
     * `null`/0 = tanpa batas. Nilai bukan nol membatasi suntingan pada jendela
     * awal setelah pesan dikirim, sehingga percakapan yang sudah lama berjalan
     * tidak dapat diubah belakangan.
     */
    'edit_window_minutes' => env('CHAT_EDIT_WINDOW_MINUTES') !== null
        ? (int) env('CHAT_EDIT_WINDOW_MINUTES')
        : null,

    /*
     * Batas jumlah balasan (utas) yang ditampilkan untuk satu pesan induk.
     * Dipakai panel utas agar satu pesan populer tidak menarik ratusan baris
     * sekaligus.
     */
    'thread_preview_limit' => (int) env('CHAT_THREAD_PREVIEW_LIMIT', 20),

    /*
     * Jendela "sedang membuka percakapan" (detik).
     *
     * Notifikasi pesan tidak dikirim ke anggota yang SEDANG membuka
     * percakapan itu (PRD §11). Karena transport-nya masih polling — jadi
     * tidak ada koneksi persisten yang bisa dipakai untuk mendeteksi
     * kehadiran — kehadiran disimpulkan dari `conversation_members.last_read_at`:
     * halaman percakapan yang terbuka memperbarui nilai itu setiap poll.
     * Bila nilainya lebih baru dari jendela ini, penerima dianggap sedang
     * membaca dan notifikasi dilewati.
     */
    'viewing_window_seconds' => (int) env('CHAT_VIEWING_WINDOW_SECONDS', 15),

    /*
     * Interval polling sisi klien, dalam milidetik (PRD §9).
     *
     * `messages` adalah jeda di halaman percakapan yang SEDANG dibuka: di sana
     * pengguna memang menunggu balasan, jadi jedanya tetap pendek dan sengaja
     * TIDAK ikut melambat. `conversations` (daftar /chat) dan `unread` (badge
     * sidebar/topbar) tampil di latar, jadi keduanya memakai backoff di bawah.
     *
     * Angka-angka ini adalah jeda TERCEPAT, bukan jeda rata-rata: selama masih
     * ada perubahan, jeda tetap sebesar ini.
     */
    'poll' => [
        'messages' => 5000,
        'conversations' => 15000,
        'unread' => 10000,

        /*
         * Backoff untuk poller yang tampil di latar (badge & daftar
         * percakapan), dihitung dalam SIKLUS — bukan milidetik.
         *
         * Setelah `idle_after` siklus berturut-turut tanpa perubahan apa pun,
         * jeda dikalikan `idle_multiplier` (badge 10 dtk -> 40 dtk, daftar
         * 15 dtk -> 60 dtk). Hitungan kembali ke nol begitu ada perubahan ATAU
         * ada pemicu peristiwa (tab/jendela kembali aktif, berpindah
         * percakapan, badge berubah), jadi halaman yang sedang dipakai tetap
         * responsif sementara halaman yang menganggur tidak terus-menerus
         * membangunkan server.
         *
         * Ini yang membuat interval dasar boleh dibuat pendek tanpa membuat
         * bebannya naik terus: yang panjang adalah ekornya, bukan jeda normal.
         */
        'idle_after' => 6,
        'idle_multiplier' => 4,
    ],
];
