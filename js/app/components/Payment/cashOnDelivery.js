import React from 'react'
import { useTranslation } from 'react-i18next'

export const Disclaimer = () => {

  const { t } = useTranslation()

  return (
    <div id="cash_on_delivery_disclaimer" className="alert alert-warning mt-4">{ t('CASH_ON_DELIVERY_DISCLAIMER') }</div>
  )
}
