import React from 'react'
import { useTranslation } from 'react-i18next'

import ShippingTimeRange from '../../../components/ShippingTimeRange'
import moment from 'moment'

export default function Time({ timeRange, allowEdit, onClick }) {
  const { t } = useTranslation()

  const isToday = moment().isSame(timeRange[0], 'day')

  const cssClasses = [ 'cart__time', 'd-flex', 'justify-content-between' ]
  if (!isToday) {
    cssClasses.push('cart__time--not-today')
  }

  return (
    <div className={ cssClasses.join(' ') } data-testid="cart.time">
        <span className="cart__time__text">
          <ShippingTimeRange value={ timeRange } />
        </span>
      { allowEdit ? (
        <a className="pl-3 text-decoration-none" href="#"
           onClick={ e => {
             e.preventDefault()
             onClick()
           } }>
            <span className="cart__time__edit">{
              t('CART_DELIVERY_TIME_EDIT') }
            </span>
        </a>
      ) : null }
    </div>
  )
}
