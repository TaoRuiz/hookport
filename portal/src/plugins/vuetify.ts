import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'

// Dates follow the browser language, like the dates formatted with toLocaleString();
// Vuetify texts missing in that language fall back to English, the language of the portal.
export default createVuetify({
  locale: { locale: navigator.language, fallback: 'en' },
})
