import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],
    safelist: [
        'bg-sky-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#01579B',
                    50: '#E1F5FE',
                    100: '#B3E5FC',
                    200: '#81D4FA',
                    300: '#4FC3F7',
                    400: '#29B6F6',
                    500: '#0288D1',
                    600: '#0288D1',
                    700: '#01579B',
                    800: '#013157',
                    900: '#01254D',
                },
                secondary: {
                    DEFAULT: '#1E88E5',
                    50: '#E3F2FD',
                    100: '#BBDEFB',
                    200: '#90CAF9',
                    300: '#64B5F6',
                    400: '#42A5F5',
                    500: '#1E88E5',
                    600: '#039BE5',
                    700: '#0288D1',
                    800: '#0277BD',
                    900: '#01579B',
                },
                accent: {
                    DEFAULT: '#FBC02D',
                    50: '#FFFDE7',
                    100: '#FFF9C4',
                    200: '#FFF59D',
                    300: '#FFF176',
                    400: '#FFEE58',
                    500: '#FBC02D',
                    600: '#F59E0B',
                    700: '#F57F17',
                    800: '#F9A825',
                    900: '#F57F17',
                },
                ink: '#1E293B',
                canvas: '#F8F9FA',
            },
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                display: ['Baloo 2', 'Fredoka', 'system-ui', 'sans-serif'],
            },
            boxShadow: {
                card: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
                'card-hover': '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            },
        },
    },
    plugins: [forms],
};