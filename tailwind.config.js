import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Paleta de marca Servimotos Fruna (Pantone): rojo #F42334, gris #C3BDBD,
// azul #253F51 y blanco. El rojo de botones es más oscuro (#D0142A) para que
// el texto blanco cumpla contraste; #F42334 queda para acentos.
const rojo = {
    50: '#FEF2F3',
    100: '#FDE7E9',
    200: '#FBC6CB',
    300: '#F7939C',
    400: '#F55A67',
    500: '#F42334',
    600: '#D0142A',
    700: '#A30F20',
    800: '#7F0C19',
    900: '#5E0913',
    950: '#3A050B',
};

const azul = {
    50: '#F1F5F8',
    100: '#E3EBF1',
    200: '#C5D4E0',
    300: '#9AB2C5',
    400: '#6A8AA3',
    500: '#46677F',
    600: '#34536A',
    700: '#253F51',
    800: '#1C3242',
    900: '#142532',
    950: '#0C1820',
};

// Grises cálidos que terminan en el azul de marca: así todo el texto
// existente (text-gray-*) queda en la paleta sin tocar cada vista.
const gris = {
    50: '#F8F6F6',
    100: '#F3F1F1',
    200: '#E2DDDD',
    300: '#CFC9C9',
    400: '#8A9AA6',
    500: '#5E6E7A',
    600: '#4A5B68',
    700: '#3B5161',
    800: '#2C4456',
    900: '#253F51',
    950: '#1C3242',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                brand: rojo,
                navy: azul,
                indigo: rojo,
                gray: gris,
                plata: '#C3BDBD',
            },
            fontFamily: {
                sans: ['Barlow', ...defaultTheme.fontFamily.sans],
                display: ['"Barlow Condensed"', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
