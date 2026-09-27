/**
 * God-Win Daycare & Nursery School — Tailwind CSS design system.
 *
 * Brand palette (from the official school poster):
 *   Primary  · Deep Pink / Magenta  #D81B60  — buttons, badges, headers
 *   Secondary· Bright Blue           #0288D1  — footer, accents, charts
 *   Accent   · Warm Gold             #FBC02D  — banners, callouts, highlights
 *   Canvas   · Light Gray            #F8F9FA  — dashboard background
 *   Ink      · Dark Slate            #1E293B  — headings / body text
 */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,jsx}',
    ],
    theme: {
        extend: {
            colors: {
                // Brand palette ------------------------------------------------
                primary: {
                    DEFAULT: '#D81B60',
                    50: '#FDEEF4',
                    100: '#FBD9E7',
                    200: '#F7B3CF',
                    300: '#F28DB7',
                    400: '#EC679F',
                    500: '#D81B60',
                    600: '#BE1755',
                    700: '#9E1346',
                    800: '#7E0F37',
                    900: '#5E0B28',
                },
                secondary: {
                    DEFAULT: '#0288D1',
                    50: '#E6F4FC',
                    100: '#C7E7F8',
                    200: '#8FCFEF',
                    300: '#57B7E7',
                    400: '#2B9FDB',
                    500: '#0288D1',
                    600: '#0277B7',
                    700: '#02669D',
                    800: '#025583',
                    900: '#013E5E',
                },
                accent: {
                    DEFAULT: '#FBC02D',
                    100: '#FEF4D7',
                    200: '#FDE9AF',
                    300: '#FCDE87',
                    400: '#FCD35F',
                    500: '#FBC02D',
                    600: '#E0A913',
                    700: '#B88A0F',
                },
                canvas: '#F8F9FA',
                ink: '#1E293B',
            },
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'Nunito', 'system-ui', 'sans-serif'],
                display: ['Baloo 2', 'Fredoka', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 3px rgba(30, 41, 59, 0.06), 0 8px 24px -12px rgba(30, 41, 59, 0.12)',
                'card-hover': '0 4px 12px rgba(30, 41, 59, 0.10), 0 16px 32px -16px rgba(216, 27, 96, 0.25)',
                glow: '0 0 0 4px rgba(216, 27, 96, 0.12)',
            },
            backgroundImage: {
                'hero-pattern':
                    "radial-gradient(circle at 20% 20%, rgba(216,27,96,0.08) 0, transparent 45%), radial-gradient(circle at 80% 60%, rgba(2,136,209,0.08) 0, transparent 45%)",
            },
            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(16px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'pop-scale': {
                    '0%': { opacity: '0', transform: 'scale(0.96)' },
                    '100%': { opacity: '1', transform: 'scale(1)' },
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' },
                },
            },
            animation: {
                'fade-up': 'fade-up 0.5s ease-out both',
                'pop-scale': 'pop-scale 0.35s cubic-bezier(0.22, 1, 0.36, 1) both',
                marquee: 'marquee 28s linear infinite',
            },
        },
    },
    plugins: [],
};