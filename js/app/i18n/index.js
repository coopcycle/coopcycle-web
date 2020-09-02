/*
 * i18n initialisation
 *
 * Initialise the i18n instance to be used in the component hierarchy
 *
 * See https://react.i18next.com/components/i18next-instance.html
 */
import i18next from 'i18next'
import { initReactI18next } from 'react-i18next'

import moment from 'moment'
import 'moment-timezone'

import de from './locales/de.json'
import en from './locales/en.json'
import es from './locales/es.json'
import fr from './locales/fr.json'
import pl from './locales/pl.json'
import pt_BR from './locales/pt_BR.json'

import de_DE from 'antd/es/locale/de_DE'
import en_US from 'antd/es/locale/en_US'
import es_ES from 'antd/es/locale/es_ES'
import fr_FR from 'antd/es/locale/fr_FR'
import pl_PL from 'antd/es/locale/pl_PL'
import antd_pt_BR from 'antd/es/locale/pt_BR'

import numbro from 'numbro'
// Use minified language files to avoid syntax error
// @see https://github.com/BenjaminVanRyseghem/numbro/pull/413
import deDE from 'numbro/dist/languages/de-DE.min.js'
import enGB from 'numbro/dist/languages/en-GB.min.js'
import esES from 'numbro/dist/languages/es-ES.min.js'
import frFR from 'numbro/dist/languages/fr-FR.min.js'
import plPL from 'numbro/dist/languages/pl-PL.min.js'
import ptBR from 'numbro/dist/languages/pt-BR.min.js'

export const localeDetector = () => $('html').attr('lang')

// https://www.i18next.com/misc/creating-own-plugins.html#languagedetector
const languageDetector = {
  type: 'languageDetector',
  detect: localeDetector,
  init: () => { },
  cacheUserLanguage: () => { }
}

i18next
  .use(languageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    resources: { de, en, fr, es, pl, "pt-BR": pt_BR },
    ns: ['common'],
    defaultNS: 'common',
    debug: process.env.DEBUG
  })

export default i18next

export const setTimezone = timezone => moment.tz.setDefault(timezone)

const antdLocaleMap = {
  'de': de_DE,
  'en': en_US,
  'es': es_ES,
  'fr': fr_FR,
  'pl': pl_PL,
  'pt-BR': antd_pt_BR,
}

// Load Numbro locales
numbro.registerLanguage(deDE)
numbro.registerLanguage(enGB)
numbro.registerLanguage(esES)
numbro.registerLanguage(frFR)
numbro.registerLanguage(plPL)
numbro.registerLanguage(ptBR)

numbro.setLanguage(localeDetector())

export const antdLocale =
  Object.prototype.hasOwnProperty.call(antdLocaleMap, localeDetector()) ? antdLocaleMap[localeDetector()] : en_US

let country

export function getCountry() {
  if (!country) {
    country = document.querySelector('body').dataset.country
  }

  return country
}

let currencySymbol

export function getCurrencySymbol() {
  if (!currencySymbol) {
    currencySymbol = document.querySelector('body').dataset.currencySymbol
  }

  return currencySymbol
}
