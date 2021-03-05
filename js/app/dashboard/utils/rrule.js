import { localeDetector } from '../../i18n'
import moment from 'moment'

const TOKENS = {
  fr: {
    every: 'chaque',
    week: 'semaine',
    on: 'le'
  },
  es: {
    every: 'cada',
    week: 'semana',
    on: 'el'
  }
}

const tokenForLocale = (locale) => {

  return (token) => {
    if (Object.prototype.hasOwnProperty.call(TOKENS, locale)
      && Object.prototype.hasOwnProperty.call(TOKENS[locale], token)) {
      return TOKENS[locale][token]
    }

    return `<${token}>`
  }
}

const LANGS = {
  fr: [
    tokenForLocale('fr'),
    {
      dayNames: moment.localeData('fr').weekdays()
    }
  ],
  es: [
    tokenForLocale('es'),
    {
      dayNames: moment.localeData('es').weekdays()
    }
  ]
}

export const toTextArgs = () => {
  const locale = localeDetector()

  if (Object.prototype.hasOwnProperty.call(LANGS, locale)) {
    return LANGS[locale]
  }

  return []
}
