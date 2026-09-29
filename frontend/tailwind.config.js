/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./src/pages/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/components/**/*.{js,ts,jsx,tsx,mdx}",
    "./src/app/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      colors: {
        traveloka: {
          blue: '#1ba0e2',
          darkBlue: '#0d5071',
          orange: '#ff5e1f',
          lightGrey: '#f5f7fa'
        }
      }
    },
  },
  plugins: [],
};
