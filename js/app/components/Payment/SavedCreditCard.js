import React from 'react'
import { useTranslation } from 'react-i18next'

import alipay from 'payment-icons/min/flat/alipay.svg'
import amex from 'payment-icons/min/flat/amex.svg'
import diners from 'payment-icons/min/flat/diners.svg'
import discover from 'payment-icons/min/flat/discover.svg'
import elo from 'payment-icons/min/flat/elo.svg'
import hipercard from 'payment-icons/min/flat/hipercard.svg'
import jcb from 'payment-icons/min/flat/jcb.svg'
import maestro from 'payment-icons/min/flat/maestro.svg'
import mastercard from 'payment-icons/min/flat/mastercard.svg'
import paypal from 'payment-icons/min/flat/paypal.svg'
import unionpay from 'payment-icons/min/flat/unionpay.svg'
import verve from 'payment-icons/min/flat/verve.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import defaultCard from 'payment-icons/min/flat/default.svg'

export default ({card}) => {

  const { t } = useTranslation()

  const creditCardIcon = () => {
    const availableIcons = {
      'alipay': alipay, 'amex': amex, 'diners': diners, 'discover': discover,
      'elo': elo, 'hipercard': hipercard, 'jcb': jcb, 'maestro': maestro,
      'mastercard': mastercard, 'paypal': paypal, 'unionpay': unionpay,
      'verve': verve, 'visa': visa
    }

    if (Object.keys(availableIcons).includes(card.brand.toLowerCase())) {
      return (
        <img src={ availableIcons[card.brand.toLowerCase()] } height={ 36 }  className="mr-4" />
      )
    } else {
      return (
        <label className="mr-4">
          <img src={ defaultCard } height={ 36 } className="mr-2" />
          { card.brand.toUpperCase() }
        </label>
      )
    }
  }

  return (
    <div className="d-flex align-items-center">
      { creditCardIcon() }
      <div className="d-flex flex-column">
        <label className="mb-0">···· { card.last4 }</label>
        <small>{ t('EXPIRATION') }: { card.exp_month }/{ card.exp_year }</small>
      </div>
    </div>
  )
}
