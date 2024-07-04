import React from 'react'

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
  useGetOrderTimingQuery,
  useUpdateOrderMutation,
} from '../../../api/slice'
import {
  selectOrderNodeId,
  setShippingTimeRange,
} from '../../../entities/order/reduxSlice'
import LoadingIcon from '../../core/LoadingIcon'
import TimeSlotPicker from '../TimeSlotPicker'
import DatePicker from '../DatePicker'
import Button from '../../core/Button'
import Alert from '../../core/Alert'
import { useMatomo } from '../../../hooks/useMatomo'

function useChooseRestaurant() {
  const dispatch = useDispatch()

  return () => {
    dispatch(closeTimeRangeChangedModal())
    window.location.href = window.Routing.generate('restaurants')
  }
}

function LoadingContent() {
  const { t } = useTranslation()
  const chooseRestaurant = useChooseRestaurant()

  return (
    <>
      <div className="ReactModal__Content__body">
        <p>{t('CART_TIME_RANGE_CHANGED_MODAL_CHOOSE_TIME_RANGE_TEXT')}</p>
        <p>
          <LoadingIcon />
        </p>
      </div>
      <div className="ReactModal__Content__buttons">
        <Button
          primary
          onClick={() => {
            chooseRestaurant()
          }}>
          {t('CART_TIME_RANGE_CHANGED_MODAL_CHOOSE_RESTAURANT_ACTION')}
        </Button>
      </div>
    </>
  )
}

function ChooseTimeRangeContent({ orderNodeId, timing }) {
  const [
    updateOrder,
    { isLoading: isUpdatingTiming, isError: isFailedToUpdateTiming },
  ] = useUpdateOrderMutation()

  const [value, setValue] = React.useState(_.first(timing.ranges))

  const { t } = useTranslation()

  const dispatch = useDispatch()
  const chooseRestaurant = useChooseRestaurant()

  return (
    <>
      <div className="ReactModal__Content__body">
        <p>{t('CART_TIME_RANGE_CHANGED_MODAL_CHOOSE_TIME_RANGE_TEXT')}</p>
        <div>
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
      </div>
      <div className="ReactModal__Content__buttons">
        <Button
          link
          onClick={() => {
            chooseRestaurant()
          }}>
          {t('CART_TIME_RANGE_CHANGED_MODAL_CHOOSE_RESTAURANT_ACTION')}
        </Button>
        <div data-testid="order.timeRangeChangedModal.setTimeRange">
          <Button
            primary
            loading={isUpdatingTiming}
            onClick={() => {
              updateOrder({
                nodeId: orderNodeId,
                shippingTimeRange: value,
              }).then(result => {
                if (result.error) {
                  //error will be handled via isError prop
                  return
                }

                dispatch(setShippingTimeRange(value))

                dispatch(setPersistedTimeRange(null))
                window.sessionStorage.removeItem(
                  getTimingPathForStorage(orderNodeId),
                )

                dispatch(closeTimeRangeChangedModal())
              })
            }}>
            {t('CART_TIME_RANGE_CHANGED_MODAL_SELECT_TIME_RANGE_ACTION')}
          </Button>
        </div>
      </div>
    </>
  )
}

function ChooseRestaurantContent() {
  const { t } = useTranslation()
  const chooseRestaurant = useChooseRestaurant()

  return (
    <>
      <div className="ReactModal__Content__body"></div>
      <div className="ReactModal__Content__buttons">
        <Button
          primary
          onClick={() => {
            chooseRestaurant()
          }}>
          {t('CART_TIME_RANGE_CHANGED_MODAL_CHOOSE_RESTAURANT_ACTION')}
        </Button>
      </div>
    </>
  )
}

function Content({ isModalOpen }) {
  const orderNodeId = useSelector(selectOrderNodeId)

  const { data: timing, isFetching: isFetchingTiming } = useGetOrderTimingQuery(
    orderNodeId,
    {
      skip: !isModalOpen,
    },
  )

  if (isFetchingTiming) {
    return <LoadingContent />
  }

  const hasTimingOptions = timing && timing.ranges.length > 0
  if (!hasTimingOptions) {
    return <ChooseRestaurantContent />
  }

  return <ChooseTimeRangeContent orderNodeId={orderNodeId} timing={timing} />
}

export default function TimeRangeChangedModal() {
  const isModalOpen = useSelector(selectIsTimeRangeChangedModalOpen)

  const { t } = useTranslation()
  const { trackEvent } = useMatomo()

  return (
    <Modal
      isOpen={isModalOpen}
      onAfterOpen={() => {
        trackEvent('Checkout', 'openModal', 'timeRangeChanged')
      }}
      contentLabel={t('CART_CHANGE_TIME_MODAL_LABEL')}
      className="TimeRangeChangedModal__Content">
      <div data-testid="order.timeRangeChangedModal">
        <h4>{t('CART_TIME_RANGE_CHANGED_MODAL_TITLE')}</h4>
        <Alert warning icon="warning">
          {t('CART_TIME_RANGE_CHANGED_MODAL_MESSAGE')}
        </Alert>
        <Content isModalOpen={isModalOpen} />
      </div>
    </Modal>
  )
}
