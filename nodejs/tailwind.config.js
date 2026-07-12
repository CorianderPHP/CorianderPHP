/** @type {import('tailwindcss').Config} */
export default {
  content: ["../public/public_views/**/*.php", "../public/**/*.php","../public/*.php","./src/**/*.ts","../src/**/*.php"],
  darkMode: 'selector',
  theme: {
    extend: {
      letterSpacing: {
        '1': '1px',
        '2': '2px',
        '4': '4px',
      },
      fontFamily: {
        'poppins': ['"Poppins"', 'sans-serif'],
        'concert-one': ['"ConcertOne"', 'sans-serif'],
      },
      colors: {
        'black': '#111827',
        'true-black': '#000000',
        'mint': '#8FE3B4',
        'white': '#F6FAF7',
        'true-white': '#FFFFFF',
        'dark-green': '#0F6B4F'
      },
      boxShadow: {
        'white': 'inset 0px 0px 30px 35px #F6FAF7',
        'black': 'inset 0px 0px 30px 35px #111827',
        'outer-white': '0px 0px 30px 35px #F6FAF7',
        'outer-black': '0px 0px 30px 35px #111827',
      },
      animation: {
        'slow-bounce': 'slow-bounce 3s infinite ease',
      },
      keyframes: {
        'slow-bounce': {
          '0%, 100%': { transform: 'translateY(0%)' },
          '50%': { transform: 'translateY(20%)' },
        }
      },
      screens: {
        'hsm': { 'raw': '(max-height: 668px) and (min-width: 640px)' }
      }
    },
  },
  plugins: [],
}
