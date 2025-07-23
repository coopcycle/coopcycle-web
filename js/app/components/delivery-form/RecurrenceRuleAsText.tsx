import React from 'react'
import { useTranslation } from 'react-i18next'
import { RRule } from 'rrule'

import { localeDetector } from '../../i18n'
import moment from 'moment'

const tokenForLocale = (locale: string, t: (key: string) => string) => {
  return (token: string): string => {
    const transKey = `RRULE.${token}`
    const translated = t(transKey)

    if (translated === transKey) {
      return token
    }

    return translated
  }
}

export const toTextArgs = (t: (key: string) => string): [Function, { dayNames: string[]; monthNames: string[] }] => {
  const locale = localeDetector()

  return [
    tokenForLocale(locale, t),
    {
      dayNames: moment.localeData(locale).weekdays(),
      monthNames: moment.localeData(locale).months()
    },
  ]
}

type Props = {
  rrule: RRule
}

const AsText = ({ rrule }: Props) => {
  const { t } = useTranslation()

  return <span>{rrule.toText(...toTextArgs(t))}</span>
}

export default AsText
