import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * AGENT: asisten | 2026-10-06 | token Tema A "Console": brand-600 teal, netral ink | UI/UX
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            /*
             * Palet "Tema A - Console" (lihat public/preview/a.html).
             *
             * Satu aksen saja (teal) di atas tangga netral murni. Netral murni
             * dipilih, bukan slate kebiruan, karena semburat biru pada abu-abu
             * membuat antarmuka terasa seperti templat generik — justru kesan
             * "AI slop" yang ingin dihindari (PRD Frontend §10).
             *
             * brand-600 dipakai tombol/link, jadi nilainya = aksen Console
             * (#0f766e) agar aksi utama dan sorotan aktif berwarna sama.
             */
            colors: {
                brand: {
                    50: '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0f766e',
                    700: '#115e59',
                    800: '#134e4a',
                    900: '#0b3b38',
                    950: '#042f2e',
                },
                ink: {
                    50: '#f6f7f9',
                    100: '#eceef1',
                    200: '#e3e6ea',
                    300: '#cbd1d9',
                    400: '#9aa3ae',
                    500: '#6b7480',
                    600: '#4e5762',
                    700: '#3b434e',
                    800: '#2b3138',
                    900: '#1b1f26',
                    950: '#14181d',
                },
            },
            /*
             * Bayangan sengaja datar dan tipis (PRD Frontend §10: tanpa glow,
             * tanpa efek mengambang). `glow` DIHAPUS, bukan sekadar tidak
             * dipakai: selama ia ada, ia akan dipakai lagi.
             */
            boxShadow: {
                card: '0 1px 2px 0 rgb(20 24 29 / 0.04), 0 1px 3px 0 rgb(20 24 29 / 0.06)',
                'card-hover': '0 4px 12px -6px rgb(20 24 29 / 0.10), 0 2px 4px -2px rgb(20 24 29 / 0.06)',
                soft: '0 2px 10px -2px rgb(20 24 29 / 0.08)',
                inset: 'inset 0 1px 0 0 rgb(255 255 255 / 0.06)',
            },
            borderRadius: {
                xl: '0.75rem',
                '2xl': '1rem',
                '3xl': '1.25rem',
            },
            keyframes: {
                'fade-in-up': {
                    from: { opacity: '0', transform: 'translateY(8px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'fade-in': {
                    from: { opacity: '0' },
                    to: { opacity: '1' },
                },
            },
            animation: {
                'fade-in-up': 'fade-in-up 0.35s cubic-bezier(0.22, 1, 0.36, 1) both',
                'fade-in': 'fade-in 0.3s ease-out both',
            },
            backgroundImage: {
                'grid-ink': 'linear-gradient(to right, rgb(15 23 42 / 0.04) 1px, transparent 1px), linear-gradient(to bottom, rgb(15 23 42 / 0.04) 1px, transparent 1px)',
            },
            backgroundSize: {
                grid: '28px 28px',
            },
        },
    },

    plugins: [forms],
};