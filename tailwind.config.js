/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./app/Filament/**/*.php",
        "./app/View/Components/**/*.php",
    ],
    safelist: [
        "bg-orange-100",
        "text-orange-800",
        "bg-green-100",
        "text-green-800",
        "bg-yellow-100",
        "text-yellow-800",
        "bg-gray-100",
        "text-gray-700",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
