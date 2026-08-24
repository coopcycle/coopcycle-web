import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { isTimeRangeSignificantlyDifferent } from '../../../utils/order/helpers'
import ShippingTimeRange from '../../ShippingTimeRange'
import {
  selectFulfilmentTimeRange,
} from '../../../entities/order/selectors'
import {
  openTimeRangeChangedModal,
  openChangeTimeRangeModal,
  selectPersistedTimeRange,
} from './reduxSlice'
import ChangeTimeRangeModal from './ChangeTimeRangeModal'
import { useGetOrderTimingQuery } from '../../../api/slice'
import {
  selectOrderNodeId,
  selectShippingTimeRange,
} from '../../../entities/order/reduxSlice'

export default function TimeRange() {
  const orderNodeId = useSelector(selectOrderNodeId)

  const { t } = useTranslation()

  const shippingTimeRange = useSelector(selectShippingTimeRange)
  const persistedTimeRange = useSelector(selectPersistedTimeRange)

  const fulfilmentTimeRange = useSelector(selectFulfilmentTimeRange)

  const { isLoading } = useGetOrderTimingQuery(orderNodeId)

  const [ isModalShown, setIsModalShown ] = useState(false)

  const dispatch = useDispatch()

  useEffect(() => {
    if (isModalShown) {
      return
    }

    if (shippingTimeRange) {
      return
    }

    if (persistedTimeRange && fulfilmentTimeRange) {
      if (isTimeRangeSignificantlyDifferent(persistedTimeRange,
        fulfilmentTimeRange)) {
        setIsModalShown(true)
        dispatch(openTimeRangeChangedModal())
      }
    }
  }, [ shippingTimeRange, persistedTimeRange, fulfilmentTimeRange ])

  if (isLoading) {
    return (<span className="loading loading-spinner loading-md"></span>)
  }

  return (
    <>
      <span className="text-success">
        <i className="fa fa-clock-o fa-lg mr-2"></i>
        <strong data-testid="order.time">
          <ShippingTimeRange value={ fulfilmentTimeRange } />
        </strong>
        <a href="#"
          className="ml-2"
          data-testid="order.time.edit"
          onClick={ e => {
            e.preventDefault()
            dispatch(openChangeTimeRangeModal())
          } }>
          { t('CART_DELIVERY_TIME_EDIT') }
        </a>
      </span>
      <ChangeTimeRangeModal />
    </>
  )
}
