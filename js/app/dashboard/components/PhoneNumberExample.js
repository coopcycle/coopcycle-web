import React from 'react'
import phoneNumberExamples from 'libphonenumber-js/examples.mobile.json'
import { getExampleNumber } from 'libphonenumber-js'
import { useTranslation } from 'react-i18next'

import { getCountry } from '../../i18n'

export default () => {

  const { t } = useTranslation()

  const country = (getCountry() || 'fr').toUpperCase()
  const phoneNumber = getExampleNumber(country, phoneNumberExamples)

  return t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_HELP', { example: phoneNumber.formatNational() })
}
