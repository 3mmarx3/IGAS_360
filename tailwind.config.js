/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './*.php',
    './modules/**/*.php',
    './includes/**/*.php',
    './auth/**/*.php',
    './assets/**/*.js'
  ],
  theme: {
    extend: {
      colors: {
        ink: {
          DEFAULT: '#0A1B26', // Liquid Nitrogen Ink
          deep: '#050D13',
          soft: '#162C3D',
          hover: '#0E2230',
        },
        primary: {
          DEFAULT: '#0B6E8A', // Cryo Teal
          bright: '#19B5C9',  // Frost Cyan
          soft: '#E3F6F9',
          dark: '#084C61',
        },
        accent: {
          DEFAULT: '#FF6B1A', // Safety Valve Orange
          hover: '#E55A0E',
          soft: '#FFF0E6',
          dark: '#B34305',
        },
        surface: {
          0: 'var(--surface-0)',
          1: 'var(--surface-1)',
          2: 'var(--surface-2)',
        },
        line: {
          DEFAULT: 'var(--line)',
          soft: 'var(--line-soft)',
          hard: 'var(--line-hard)',
        },
        status: {
          success: '#1FA971', // Purged/OK
          warning: '#F2B01E', // Caution
          danger: '#D93636',  // Hazard
          info: '#2F80ED',
        },
        gas: {
          o2: '#0066CC',      // Oxygen (Blue)
          n2: '#1F2937',      // Nitrogen (Black / Dark Slate)
          ar: '#047857',      // Argon (Dark Green)
          co2: '#6B7280',     // Carbon Dioxide (Gray)
          c2h2: '#881337',    // Acetylene (Maroon)
          h2: '#DC2626',      // Hydrogen (Red)
          he: '#78350F',      // Helium (Brown)
          air: '#374151',     // Compressed Air (Black / White)
          lpg: '#EA580C',     // LPG (Orange/Red)
          n2o: '#2563EB',     // Nitrous Oxide (Blue)
        }
      },
      fontFamily: {
        display: ['Oxanium', 'Reem Kufi', 'sans-serif'],
        sans: ['IBM Plex Sans', 'IBM Plex Sans Arabic', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
        mono: ['IBM Plex Mono', 'monospace'],
      },
      // STRICT ZERO SHADOW RULE: Disable all shadows completely
      boxShadow: {},
      dropShadow: {},
      ringWidth: {
        0: '0px',
      },
      ringColor: {},
      ringOffsetWidth: {
        0: '0px',
      },
      ringOffsetColor: {},
    },
  },
  corePlugins: {
    boxShadow: false,
    dropShadow: false,
    boxShadowColor: false,
    ringWidth: false,
    ringColor: false,
    ringOffsetWidth: false,
    ringOffsetColor: false,
  },
  plugins: [],
};
