import React, { useEffect } from 'react'

import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'

import { getTimingPathForStorage } from '../../../utils/order/helpers'
import {
  closeTimeRangeChangedModal,
  selectIsTimeRangeChangedModalOpen,
  setPersistedTimeRange,
} from './reduxSlice'
import {
  useGetOrderTimingQuery, useUpdateOrderMutation,
} from '../../../api/slice'
import {
  selectOrderNodeId,
  setShippingTimeRange,
} from '../../../entities/order/reduxSlice'
import LoadingIcon from '../../core/LoadingIcon'
import TimeSlotPicker from '../TimeSlotPicker'
import DatePicker from '../DatePicker'
import Button from '../../core/Button'

export default function TimeRangeChangedModal() {
  const isModalOpen = useSelector(selectIsTimeRangeChangedModalOpen)

  const dispatch = useDispatch()

  const orderNodeId = useSelector(selectOrderNodeId)

  const {
    data: timing, isFetching: isFetchingTiming,
  } = useGetOrderTimingQuery(orderNodeId, {
    skip: !isModalOpen,
  })

  const [
    updateOrder, {
      isLoading: isUpdatingTiming, isError: isFailedToUpdateTiming,
    } ] = useUpdateOrderMutation()

  const [ value, setValue ] = React.useState(null)

  const hasTimingOptions = timing && timing.ranges.length > 0
  const hasValue = Boolean(value)

  const { t } = useTranslation()

  useEffect(() => {
    if (!hasTimingOptions) return

    const initialValue = _.first(timing.ranges)
    setValue(initialValue)
  }, [ hasTimingOptions, timing ])

  return (<Modal
    isOpen={ isModalOpen }
    contentLabel={ t('CART_CHANGE_TIME_MODAL_LABEL') }
    className="TimeRangeChangedModal__Content">
    <div data-testid="order.timeRangeChangedModal">
      <h4>
        { t('CART_TIME_RANGE_CHANGED_MODAL_TEXT_LINE_1') }
      </h4>
      <div className="ReactModal__Content__body">
        { isFetchingTiming ? (<>
          <p>
            { t('CART_TIME_RANGE_CHANGED_MODAL_TEXT_LINE_2') }
          </p>
          <p>
            <LoadingIcon />
          </p>
        </>) : null }
        { !isFetchingTiming && hasTimingOptions && hasValue ? (<>
          <p>
            { t('CART_TIME_RANGE_CHANGED_MODAL_TEXT_LINE_2') }
          </p>
          <div className="mx-4">
            { timing.behavior === 'time_slot' ? (<TimeSlotPicker
              choices={ timing.ranges }
              value={ value }
              onChange={ value => setValue(value) } />) : null }
            { timing.behavior === 'asap' ? (<DatePicker
              choices={ timing.ranges }
              value={ value }
              onChange={ value => setValue(value) } />) : null }
            { isFailedToUpdateTiming ? (<div className="alert alert-danger">
              { t('CART_CHANGE_TIME_FAILED') }
            </div>) : null }
          </div>
          <div
            className="ReactModal__Content__buttons"
            data-testid="order.timeRangeChangedModal.setTimeRange">
            <Button
              primary
              loading={ isUpdatingTiming }
              onClick={ () => {
                updateOrder({
                  nodeId: orderNodeId, shippingTimeRange: value,
                }).then((result) => {
                  if (result.error) {
                    //error will be handled via isError prop
                    return
                  }

                  dispatch(setShippingTimeRange(value))

                  dispatch(setPersistedTimeRange(null))
                  window.sessionStorage.removeItem(
                    getTimingPathForStorage(orderNodeId))

                  dispatch(closeTimeRangeChangedModal())
                })
              } }>
              { t('CART_CHANGE_TIME_MODAL_LABEL') }
            </Button>
          </div>
          <div className="text-center font-weight-bold">
            <p>
              { t('OR') }
            </p>
          </div>
        </>) : null }
        <p>
          { t('CART_RESTAURANT_NOT_AVAILABLE_MODAL_TEXT_LINE_2') }
        </p>
      </div>
      <div className="ReactModal__Content__buttons">
        <Button
          primary
          onClick={ () => {
            dispatch(closeTimeRangeChangedModal())
            window.location.href = window.Routing.generate('restaurants')
          } }>
          { t('CART_ADDRESS_MODAL_BACK_TO_RESTAURANTS') }
        </Button>
      </div>
    </div>
  </Modal>)
}