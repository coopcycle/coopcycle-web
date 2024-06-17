import React, { useEffect, useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import BaseTimeRange from '../components/order/TimeRange'
import { isTimeRangeSignificantlyDifferent } from '../utils/order/helpers'
import { openTimeRangeChangedModal } from './redux/uiSlice'
import {
  selectFulfilmentTimeRange, selectOrderNodeId,
  selectPersistedTimeRange,
  selectShippingTimeRange,
} from './redux/selectors'
import { useGetOrderTimingQuery } from '../redux/api/slice'
import LoadingIcon from '../components/core/LoadingIcon'

export default function TimeRange() {
  const orderNodeId = useSelector(selectOrderNodeId)

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
      if (isTimeRangeSignificantlyDifferent(persistedTimeRange, fulfilmentTimeRange)) {
        setIsModalShown(true)
        dispatch(openTimeRangeChangedModal())
      }
    }
  }, [shippingTimeRange, persistedTimeRange, fulfilmentTimeRange])

  if (isLoading) {
    return (<LoadingIcon />)
  }

  return (
    <BaseTimeRange timeRange={ fulfilmentTimeRange } />
  )
}
