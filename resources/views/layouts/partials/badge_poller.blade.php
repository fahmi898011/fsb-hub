{{-- AGENT: asisten | 2026-10-10 | polling badge belum-dibaca (chat + notifikasi) | modul Chat §9 --}}
{{--
    Polling badge "belum dibaca" (PRD Chat §9, target pertama).

    Sebelum ini kedua badge hanya dihitung saat server merender halaman
    (composer topbar & sidebar di AppServiceProvider). Akibatnya penerima yang
    tidak sedang membuka percakapan itu baru melihat angkanya setelah memuat
    ulang halaman. Satu permintaan ringan tiap `chat.poll.unread` milidetik kini
    cukup untuk memperbarui badge chat (sidebar, dua varian) dan badge
    notifikasi (topbar) tanpa memuat ulang halaman mana pun.

    Jeda dan backoff-nya diserahkan ke `window.fsbPollLoop`
    (layouts/partials/poll_loop.blade.php): selama ada perubahan badge tetap
    cepat, dan halaman yang sudah lama tidak berubah melambat sendiri. Badge
    yang BERUBAH juga menyiarkan `fsb:chat-unread-changed` supaya daftar
    percakapan ikut menyegarkan seketika.

    Hasilnya dipaparkan sebagai `window.fsbRefreshBadges()` supaya halaman chat
    dapat memicunya segera setelah menandai percakapan dibaca (membuka atau
    berpindah percakapan), tanpa menunggu satu siklus penuh.
--}}
<script>
    (() => {
        // Pengaturan dibaca dari `window.fsbPollSettings` (lihat
        // layouts/partials/poll_loop.blade.php) supaya angkanya hanya ada di
        // config/chat.php. Angka cadangan di sini hanya jaring pengaman bila
        // partial itu suatu saat tidak ikut dirender.
        const settings = window.fsbPollSettings || {};
        const interval = Number(settings.unread ?? 10000);
        const idleAfter = Number(settings.idleAfter ?? 6);
        const idleMultiplier = Number(settings.idleMultiplier ?? 4);

        // Tanpa penjadwal bersama, polling dimatikan saja: badge tetap benar
        // saat halaman dimuat, jadi yang hilang hanya pembaruan langsung —
        // jauh lebih baik daripada skrip yang melempar dan menghentikan skrip
        // lain di halaman yang sama.
        if (typeof window.fsbPollLoop !== 'function') return;

        const url = @json(route('chat.unread'));

        // Badge sengaja SELALU ada di DOM (lihat sidebar & topbar), jadi angka
        // nol punya elemen untuk disembunyikan — bukan dihapus dari markup.
        // Plafon angkanya dibaca dari `data-badge-cap` karena sidebar punya dua
        // varian dengan plafon berbeda (9+ dan 99+).
        const paint = (selector, total) => {
            document.querySelectorAll(selector).forEach((node) => {
                const cap = Number(node.dataset.badgeCap || 99);

                node.textContent = total > cap ? `${cap}+` : String(total);
                node.classList.toggle('hidden', total === 0);
            });
        };

        // Angka yang dirender server dipakai sebagai titik awal, supaya siklus
        // pertama hanya dihitung "berubah" bila memang ada perbedaan. Badge
        // yang dipotong ("99+") tidak bisa dibalik menjadi angka, jadi sengaja
        // dibiarkan `null` (belum diketahui).
        const rendered = (selector) => {
            const node = document.querySelector(selector);
            const text = node ? node.textContent.trim() : '';

            return /^\d+$/.test(text) ? Number(text) : null;
        };

        let lastChat = rendered('[data-chat-badge]');
        let lastNotif = rendered('[data-notif-badge]');

        const tick = async () => {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) return false;

            const data = await response.json();
            const chat = Number(data.chat || 0);
            const notifikasi = Number(data.notifikasi || 0);
            const changed = chat !== lastChat || notifikasi !== lastNotif;

            paint('[data-chat-badge]', chat);
            paint('[data-notif-badge]', notifikasi);

            // Poller lain (daftar percakapan di /chat) memakai sinyal ini supaya
            // ikut menyegarkan SEKETIKA saat badge sudah lebih dulu tahu ada
            // pesan baru, alih-alih menunggu siklusnya sendiri.
            if (chat !== lastChat) {
                document.dispatchEvent(new CustomEvent('fsb:chat-unread-changed', {
                    detail: { count: chat },
                }));
            }

            lastChat = chat;
            lastNotif = notifikasi;

            return changed;
        };

        const loop = window.fsbPollLoop({ interval, idleAfter, idleMultiplier, tick });

        // Dipanggil halaman chat segera setelah menandai percakapan dibaca
        // (membuka atau berpindah percakapan): badge turun tanpa menunggu satu
        // siklus penuh, dan jedanya kembali ke tingkat cepat.
        window.fsbRefreshBadges = () => loop.kick();

        // Tanpa permintaan saat halaman baru dimuat: angka awalnya sudah benar
        // karena dirender server.
        loop.start();
    })();
</script>
