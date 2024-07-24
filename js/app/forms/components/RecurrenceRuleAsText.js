import React from 'react'
import { useTranslation } from 'react-i18next'

import { localeDetector } from '../../i18n'
import moment from 'moment'

const tokenForLocale = (locale, t) => {
  return token => {
    const transKey = `RRULE.${token}`
    const translated = t(transKey)

    if (translated === transKey) {
      return token
    }

    return translated
  }
}

export const toTextArgs = t => {
  const locale = localeDetector()

  return [
    tokenForLocale(locale, t),
    {
      dayNames: moment.localeData(locale).weekdays(),
    },
  ]
}

const AsText = ({ rrule }) => {
  const { t } = useTranslation()

  return <span>{rrule.toText(...toTextArgs(t))}</span>
}

export default AsText
