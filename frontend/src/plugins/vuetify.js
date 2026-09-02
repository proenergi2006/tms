/**
 * plugins/vuetify.ts
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Composables
import { createVuetify } from 'vuetify'
// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// Tema nuansa biru muda (mengikuti SYOP) — lihat Wireframe Document Bagian 4.
const lightBlueTheme = {
  dark: false,
  colors: {
    'background': '#F0F7FF',
    'surface': '#FFFFFF',
    'primary': '#0EA5E9',
    'primary-darken-1': '#0284C7',
    'secondary': '#38BDF8',
    'secondary-darken-1': '#0EA5E9',
    'error': '#EF4444',
    'info': '#0EA5E9',
    'success': '#22C55E',
    'warning': '#F59E0B',
  },
}

const lightBlueDarkTheme = {
  dark: true,
  colors: {
    'background': '#0B1D2E',
    'surface': '#0F2A44',
    'primary': '#38BDF8',
    'primary-darken-1': '#0EA5E9',
    'secondary': '#7DD3FC',
    'error': '#F87171',
    'info': '#38BDF8',
    'success': '#4ADE80',
    'warning': '#FBBF24',
  },
}

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'system',
    themes: {
      light: lightBlueTheme,
      dark: lightBlueDarkTheme,
    },
  },
})
