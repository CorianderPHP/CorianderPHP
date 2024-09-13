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
        'depixel': ['"DePixel"', 'sans-serif'],
        'poppins': ['"Poppins"', 'sans-serif'],
        'concert-one': ['"ConcertOne"', 'sans-serif'],
      },
      colors: {
        'black': '#1F1F1F',
        'true-black': '#000000',
        'peach': '#FFBE98',
        'white': '#EAE4DF',
        'true-white': '#FFFFFF',
        'dark-green': '#0A5321'
      },
      boxShadow: {
        'white': 'inset 0px 0px 30px 35px #EAE4DF',
        'black': 'inset 0px 0px 30px 35px #1F1F1F',
        'outer-white': '0px 0px 30px 35px #EAE4DF',
        'outer-black': '0px 0px 30px 35px #1F1F1F',
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