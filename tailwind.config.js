import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Barlow', ...defaultTheme.fontFamily.sans],
                condensed: ['"Barlow Condensed"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    DEFAULT: '#0A0A0A',
                    soft: '#5C5A52',
                    50: '#F2F1ED',
                    100: '#E5E3DC',
                    200: '#D8D6CE',
                    300: '#B8B5AA',
                    400: '#8A877C',
                    500: '#5C5A52',
                    600: '#3D3B35',
                    700: '#26251F',
                    800: '#151410',
                    900: '#0A0A0A',
                    950: '#000000',
                },
                paper: {
                    DEFAULT: '#FFFFFF',
                    dim: '#F2F1ED',
                },
                signal: {
                    DEFAULT: '#FFC800',
                    50: '#FFFBE6',
                    100: '#FFF3BF',
                    200: '#FFE680',
                    300: '#FFD940',
                    400: '#FFC800',
                    500: '#E6B400',
                    600: '#B38C00',
                },
                line: '#D8D6CE',
                danger: {
                    DEFAULT: '#C4342B',
                    light: '#F7E3E1',
                },
                success: {
                    DEFAULT: '#2E7D46',
                    light: '#E1F0E6',
                },
            },
            borderRadius: {
                DEFAULT: '6px',
                md: '6px',
                lg: '6px',
                xl: '6px',
            },
        },
    },

    plugins: [forms],
};