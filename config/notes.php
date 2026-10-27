<?php

/**
 * Konfigurasi modul Catatan Kerja (PRD Catatan).
 *
 * AGENT: asisten | 2026-10-01 | buat berkas baru | modul Catatan Kerja, fase 1 backend
 *
 * Batas panjang dan jumlah sengaja TIDAK di-hard-code di controller/validasi,
 * melainkan dibaca dari sini supaya dapat diubah lewat `.env` tanpa menyentuh
 * kode - mengikuti pola `config/chat.php`.
 *
 * Batas jumlah catatan penting bukan sekadar kerapian: tanpa batas, kolom
 * `longText` menjadi tempat penyimpanan tanpa batas dan halaman daftar tidak
 * pernah selesai dimuat.
 *
 * AGENT: asisten | 2026-10-06 | tambah batas label (tag_max_length, max_tags_per_user) | modul Catatan, lanjutan
 */
return [

    // Panjang maksimum satu catatan, dalam karakter (50 ribu ~ 8-10 halaman).
    'max_length' => (int) env('NOTES_MAX_LENGTH', 50000),

    // Panjang maksimum judul catatan.
    'title_max_length' => (int) env('NOTES_TITLE_MAX_LENGTH', 150),

    // Jumlah maksimum catatan AKTIF per karyawan (yang terhapus tidak dihitung).
    'max_notes_per_user' => (int) env('NOTES_MAX_PER_USER', 200),

    // Panjang maksimum nama label (PRD Catatan §14).
    'tag_max_length' => (int) env('NOTES_TAG_MAX_LENGTH', 50),

    // Jumlah maksimum label per karyawan; mencegah panel saring tak terbatas.
    'max_tags_per_user' => (int) env('NOTES_MAX_TAGS_PER_USER', 50),

    /*
     * Jeda autosave sisi klien (milidetik) sebelum teks dikirim ke server.
     * Terlalu kecil = terlalu banyak request; terlalu besar = lebih banyak
     * ketikan yang berisiko hilang saat koneksi putus.
     */
    'autosave_debounce_ms' => (int) env('NOTES_AUTOSAVE_DEBOUNCE_MS', 1500),

    /*
     * Kuota tulis per menit per pengguna. Autosave memicu paling banyak satu
     * request per `autosave_debounce_ms`, jadi ~40/menit saat mengetik terus.
     * Batas ini memberi ruang untuk koreksi manual dan beberapa tab.
     */
    'write_rate_limit' => (int) env('NOTES_WRITE_RATE_LIMIT', 180),

];
