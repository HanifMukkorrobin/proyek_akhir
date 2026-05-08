import daisyui from 'daisyui'

/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['class', '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        background: '#f6fbf0',
        surface: '#f6fbf0',
        'surface-dim': '#d6dcd1',
        'surface-bright': '#f6fbf0',
        'surface-container-lowest': '#ffffff',
        'surface-container-low': '#f0f5eb',
        'surface-container': '#eaf0e5',
        'surface-container-high': '#e4eadf',
        'surface-container-highest': '#dfe4da',
        'surface-variant': '#dfe4da',
        'surface-tint': '#006e27',
        'on-surface': '#171d17',
        'on-surface-variant': '#3f4a3e',
        'on-background': '#171d17',
        'inverse-surface': '#2c322b',
        'inverse-on-surface': '#edf3e8',
        outline: '#6f7a6c',
        'outline-variant': '#becaba',
        primary: '#006b26',
        'on-primary': '#ffffff',
        'primary-container': '#1c8637',
        'on-primary-container': '#f7fff2',
        'primary-fixed': '#93f99b',
        'primary-fixed-dim': '#78dc82',
        'on-primary-fixed': '#002107',
        'on-primary-fixed-variant': '#00531c',
        secondary: '#5d5f5f',
        'on-secondary': '#ffffff',
        'secondary-container': '#dfe0e0',
        'on-secondary-container': '#616363',
        tertiary: '#a03556',
        'on-tertiary': '#ffffff',
        'tertiary-container': '#bf4d6e',
        'on-tertiary-container': '#fffbff',
        'tertiary-fixed': '#ffd9df',
        'tertiary-fixed-dim': '#ffb1c2',
        'on-tertiary-fixed': '#3f0018',
        'on-tertiary-fixed-variant': '#841f41',
        error: '#ba1a1a',
        'on-error': '#ffffff',
        'error-container': '#ffdad6',
        'on-error-container': '#93000a',
        'forest-950': '#06140b',
        'forest-900': '#0b2a17',
        'forest-800': '#0d3f21'
      },
      borderRadius: {
        DEFAULT: '0.25rem',
        lg: '0.5rem',
        xl: '0.75rem',
        '2xl': '1rem'
      },
      boxShadow: {
        ambient: '0 8px 30px rgba(32, 137, 58, 0.08)',
        panel: '0 2px 15px -3px rgba(32, 137, 58, 0.08)',
        lifted: '0 24px 60px rgba(6, 78, 31, 0.16)'
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        mono: ['"SFMono-Regular"', 'Consolas', '"Liberation Mono"', 'monospace']
      },
      fontSize: {
        'display-lg': ['48px', { lineHeight: '56px', letterSpacing: '0', fontWeight: '700' }],
        'headline-md': ['24px', { lineHeight: '32px', letterSpacing: '0', fontWeight: '600' }],
        'title-lg': ['18px', { lineHeight: '24px', letterSpacing: '0', fontWeight: '600' }],
        'body-md': ['16px', { lineHeight: '24px', letterSpacing: '0', fontWeight: '400' }],
        'body-sm': ['14px', { lineHeight: '20px', letterSpacing: '0', fontWeight: '400' }],
        'label-caps': ['12px', { lineHeight: '16px', letterSpacing: '0.05em', fontWeight: '600' }]
      },
      spacing: {
        margin: '24px',
        gutter: '20px',
        md: '16px',
        lg: '24px',
        xl: '40px'
      }
    }
  },
  plugins: [daisyui],
  daisyui: {
    themes: [
      {
        light: {
          primary: '#006b26',
          secondary: '#5d5f5f',
          accent: '#a03556',
          neutral: '#171d17',
          'base-100': '#FFFFFF',
          'base-200': '#f6fbf0',
          'base-300': '#eaf0e5',
          'base-content': '#171d17',
          info: '#38BDF8',
          success: '#22C55E',
          warning: '#F59E0B',
          error: '#ba1a1a'
        }
      },
      {
        dark: {
          primary: '#78dc82',
          secondary: '#c6c6c7',
          accent: '#ffb1c2',
          neutral: '#edf3e8',
          'base-100': '#06140b',
          'base-200': '#0b2a17',
          'base-300': '#0d3f21',
          'base-content': '#edf3e8',
          info: '#38BDF8',
          success: '#22C55E',
          warning: '#FBBF24',
          error: '#ffb4ab'
        }
      }
    ]
  }
}
