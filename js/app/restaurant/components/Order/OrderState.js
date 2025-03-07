import React from 'react'
import { useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'

import {
  selectCartTotal,
  selectIsFetching,
  selectRestaurant,
  selectSortedErrorMessages,
} from '../../redux/selectors'

export default function OrderState() {
  const isFetching = useSelector(selectIsFetching)

  const errors = useSelector(selectSortedErrorMessages)
  const total = useSelector(selectCartTotal)

  const restaurant = useSelector(selectRestaurant)

  const { t } = useTranslation()

  const label = restaurant.isOpen
    ? t('CART_WIDGET_BUTTON')
    : t('SCHEDULE_ORDER')

  if (isFetching) {

    return (
      <i className="fa fa-spinner fa-spin"></i>
    )
  }

  if (errors.length > 0) {

    return (
      <span className="button-composite">
        <i className="fa fa-warning"></i>
        <small>{ _.first(errors) }</small>
      </span>
    )
  }

  return (
    <span className="button-composite">
      <i className="fa fa-shopping-cart"></i>
      <span>{ label }</span>
      <span>{ (total / 100).formatMoney() }</span>
    </span>
  )
}
