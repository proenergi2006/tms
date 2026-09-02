import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import router from '../router'
/**
 * plugins/index.ts
 *
 * Automatically included in `./src/main.ts`
 */
// Types
// Plugins
import i18n from './i18n'
import vuetify from './vuetify'

export function registerPlugins (app) {
  app.use(vuetify)
  app.use(createPinia())
  app.use(router)
  app.use(VueApexCharts)
  app.use(i18n)
}
