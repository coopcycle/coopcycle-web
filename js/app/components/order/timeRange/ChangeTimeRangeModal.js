import React from 'react'

import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'

import {
  closeChangeTimeRangeModal,
  selectIsChangeTimeRangeModalOpen,
} from './reduxSlice'
import {
  useGetOrderTimingQuery,
  useUpdateOrderMutation,
} from '../../../api/slice'
import {
  selectOrderNodeId,
  setShippingTimeRange,
} from '../../../entities/order/reduxSlice'
import { selectFulfilmentTimeRange } from '../../../entities/order/selectors'
import TimeSlotPicker from '../TimeSlotPicker'
import DatePicker from '../DatePicker'
import Button from '../../core/Button'
import Alert from '../../core/Alert'
import { useMatomo } from '../../../hooks/useMatomo'

function Content({ orderNodeId, timing, fulfilmentTimeRange, onClose }) {
  const [
    updateOrder,
    { isLoading: isUpdatingTiming, isError: isFailedToUpdateTiming },
  ] = useUpdateOrderMutation()

  const [value, setValue] = React.useState(fulfilmentTimeRange ?? _.first(timing.ranges))

  const { t } = useTranslation()
  const dispatch = useDispatch()

  return (
    <>
      <div className="ReactModal__Content__body">
        {timing.behavior === 'time_slot' ? (
          <TimeSlotPicker
            choices={timing.ranges}
            value={value}
            onChange={value => setValue(value)}
          />
        ) : null}
        {timing.behavior === 'asap' ? (
          <DatePicker
            choices={timing.ranges}
            value={value}
            onChange={value => setValue(value)}
          />
        ) : null}
        {isFailedToUpdateTiming ? (
          <Alert danger>{t('CART_CHANGE_TIME_FAILED')}</Alert>
        ) : null}
      </div>
      <div className="ReactModal__Content__buttons">
        <Button link onClick={onClose}>
          {t('CART_DELIVERY_TIME_CANCEL')}
        </Button>
        <div data-testid="order.changeTimeRangeModal.submit">
          <Button
            primary
            loading={isUpdatingTiming}
            onClick={() => {
              updateOrder({
                nodeId: orderNodeId,
                shippingTimeRange: value,
              }).then(result => {
                if (result.error) {
                  return
                }

                dispatch(setShippingTimeRange(value))
                onClose()
              })
            }}>
            {t('CART_DELIVERY_TIME_SUBMIT')}
          </Button>
        </div>
      </div>
    </>
  )
}

export default function ChangeTimeRangeModal() {
  const isModalOpen = useSelector(selectIsChangeTimeRangeModalOpen)
  const orderNodeId = useSelector(selectOrderNodeId)
  const fulfilmentTimeRange = useSelector(selectFulfilmentTimeRange)

  const { data: timing, isFetching: isFetchingTiming } = useGetOrderTimingQuery(
    orderNodeId,
    { skip: !isModalOpen },
  )

  const { t } = useTranslation()
  const { trackEvent } = useMatomo()

  const dispatch = useDispatch()
  const onClose = () => dispatch(closeChangeTimeRangeModal())

  return (
    <Modal
      isOpen={isModalOpen}
      onAfterOpen={() => trackEvent('Checkout', 'openModal', 'changeDate')}
      onRequestClose={onClose}
      shouldCloseOnOverlayClick={true}
      contentLabel={t('CART_CHANGE_TIME_MODAL_LABEL')}
      overlayClassName="ReactModal__Overlay"
      className="TimeRangeChangedModal__Content">
      <div data-testid="order.changeTimeRangeModal">
        <h4 className="font-bold text-lg mb-4 text-center">{t('CART_CHANGE_TIME_MODAL_TITLE')}</h4>
        {isModalOpen && !isFetchingTiming && timing ? (
          <Content
            orderNodeId={orderNodeId}
            timing={timing}
            fulfilmentTimeRange={fulfilmentTimeRange}
            onClose={onClose} />
        ) : (
          <div className="ReactModal__Content__body text-center">
            <span className="loading loading-spinner loading-md"></span>
          </div>
        )}
      </div>
    </Modal>
  )
}
