import React from 'react'
import { useTranslation } from 'react-i18next'
import clsx from 'clsx'

import ShippingTimeRange from '../../../components/ShippingTimeRange'
import moment from 'moment'

export default function Time({ timeRange, allowEdit, onClick }) {
  const { t } = useTranslation()

  const isToday = moment().isSame(timeRange[0], 'day')

  return (
    <div className={ clsx('flex gap-4 justify-between', isToday ? 'text-success' : 'text-error') } data-testid="cart.time">
      <span>
        <ShippingTimeRange value={ timeRange } />
      </span>
      { allowEdit ? (
      <a href="#"
          onClick={ e => {
            e.preventDefault()
            onClick()
          } }>
          <span>{
            t('CART_DELIVERY_TIME_EDIT') }
          </span>
      </a>
      ) : null }
    </div>
  )
}
