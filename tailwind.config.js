module.exports = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        primary: '#d92929',
        primaryDark: '#8d1111',
        soft: '#f8f3f3',
        healthcare: '#f7f7f7',
        accent: '#d93025'
      },
      boxShadow: {
        soft: '0 10px 30px rgba(217, 41, 41, 0.08)',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      }
    },
  },
  plugins: [],
};
