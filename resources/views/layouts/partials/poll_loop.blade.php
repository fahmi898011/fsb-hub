{{-- AGENT: asisten | 2026-10-10 | penjadwal polling adaptif bersama (backoff + pemicu peristiwa) | modul Chat §9 --}}
{{--
    Penjadwal polling adaptif yang dipakai bersama oleh poller badge
    (layouts/partials/badge_poller.blade.php) dan poller daftar percakapan
    (chat/index.blade.php). PRD Chat §9.

    Dua hal yang membuat pesan baru terasa cepat sampai tanpa memperpendek
    interval sampai membebani server:

    1. Dua tingkat kecepatan. Selama ada perubahan, jeda tetap CEPAT
       (`chat.poll.unread` / `chat.poll.conversations`). Setelah
       `chat.poll.idle_after` siklus berturut-turut tanpa perubahan, jeda
       dikalikan `chat.poll.idle_multiplier` — jadi halaman yang menganggur
       memakai jauh lebih sedikit permintaan daripada halaman yang aktif.
    2. Pemicu peristiwa. Kembalinya tab/jendela ke depan (`visibilitychange`,
       `focus`) langsung memicu satu penyegaran dan mengembalikan kecepatan ke
       tingkat cepat. Inilah yang paling terasa bagi pengguna: berpindah tab
       lalu kembali menampilkan data baru SEKETIKA, bukan menunggu timer.

    Polling tetap HANYA berjalan saat tab terlihat (PRD §9): browser
    men-throttle timer di tab latar, jadi menjadwalkannya di sana hanya
    menambah permintaan yang hasilnya tidak sempat dilihat.

    Penjadwalan memakai `setTimeout` berantai, bukan `setInterval`, karena jeda
    berikutnya harus dapat BERUBAH setelah tiap siklus.

    Angka-angka dari `config/chat.php` dipaparkan sekali di sini sebagai
    `window.fsbPollSettings`, supaya tidak ada skrip yang meng-hard-code
    intervalnya dan hanya ada SATU sumber angka. Karena partial ini di-@include
    dari layout SEBELUM `@stack('scripts')`, nilainya sudah tersedia saat skrip
    halaman berjalan.
--}}
{{-- Catatan penting: pengaturannya dibuat lewat variabel `@php`, BUKAN
     `@json([...])`. Direktif `@json` memecah ekspresinya berdasarkan koma
     (argumen kedua = opsi, ketiga = kedalaman), sehingga array literal
     multi-baris di dalamnya menghasilkan PHP yang tidak sah. --}}
@php($pollSettings = [
    'unread' => (int) config('chat.poll.unread', 10000),
    'conversations' => (int) config('chat.poll.conversations', 15000),
    'idleAfter' => (int) config('chat.poll.idle_after', 6),
    'idleMultiplier' => (int) config('chat.poll.idle_multiplier', 4),
])
<script>
    window.fsbPollSettings = Object.freeze(@json($pollSettings));

    /**
     * Jalankan `tick` berulang dengan jeda adaptif.
     *
     * `options.interval`, `options.idleAfter`, dan `options.idleMultiplier`
     * mengatur jedanya. `options.tick` mengembalikan `true` bila ada yang
     * berubah; itulah satu-satunya penanda bahwa pengguna masih menunggu
     * sesuatu (jadi jangan melambat). `options.canRun` dipakai pemanggil untuk
     * menahan satu siklus pada keadaan tertentu (mis. daftar percakapan yang
     * tidak boleh ditukar selama tombol mouse masih ditekan).
     *
     * Catatan: isi `<script>` di berkas Blade TETAP diproses Blade, jadi jangan
     * menulis pasangan kurung kurawal ganda di komentar JS (mis. tipe gaya
     * JSDoc) — Blade membacanya sebagai echo dan PHP hasilnya jadi tidak sah.
     */
    window.fsbPollLoop = (options) => {
        const interval = Math.max(1, Number(options.interval) || 10000);
        const idleAfter = Math.max(1, Number(options.idleAfter) || 1);
        const idleMultiplier = Math.max(1, Number(options.idleMultiplier) || 1);

        let timer = null;
        let busy = false;
        let idleCycles = 0;

        const delay = () => (idleCycles >= idleAfter ? interval * idleMultiplier : interval);

        const stop = () => {
            if (timer) clearTimeout(timer);
            timer = null;
        };

        const refresh = async () => {
            // Jaringan tersendat dapat membuat permintaan menumpuk; satu
            // permintaan berjalan pada satu waktu sudah cukup.
            if (busy || document.hidden) return;
            if (options.canRun && !options.canRun()) return;

            busy = true;

            try {
                const changed = await options.tick();
                idleCycles = changed ? 0 : idleCycles + 1;
            } catch (e) {
                // Gagal menyegarkan bukan kejadian fatal: tampilan lama tetap
                // ada dan siklus berikutnya menyusul. Tetap dihitung sebagai
                // siklus menganggur supaya kegagalan beruntun tidak berubah
                // menjadi percobaan bertubi-tubi.
                idleCycles += 1;
            } finally {
                busy = false;
            }
        };

        const schedule = () => {
            stop();

            if (document.hidden) return;

            timer = setTimeout(async () => {
                await refresh();
                schedule();
            }, delay());
        };

        /**
         * Kembali ke tingkat cepat dan menyegarkan SEKARANG.
         *
         * Dipakai pemicu peristiwa (tab/jendela kembali aktif, berpindah
         * percakapan) dan dipaparkan ke halaman lain lewat
         * `window.fsbRefreshBadges()`.
         */
        const kick = async () => {
            stop();
            idleCycles = 0;
            await refresh();
            schedule();
        };

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
                return;
            }

            kick();
        });

        // `focus` pada `window`, bukan `document`: berpindah dari jendela lain
        // belum tentu mengubah visibilitas tab, padahal datanya sudah basi.
        window.addEventListener('focus', () => { kick(); });

        // `start()` sengaja TIDAK menyegarkan seketika: angka/markup awal sudah
        // dirender server, jadi permintaan pertama baru berguna setelah satu
        // jeda penuh.
        return { start: schedule, stop, kick, refresh };
    };
</script>
