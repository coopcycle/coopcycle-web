import React, { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import _ from 'lodash'

import {
  selectCart,
  selectFulfilmentMethod,
  selectFulfillmentRelatedErrorMessages,
  selectFulfilmentTimeRange,
  selectCanAddToExistingCart,
  selectIsOrderAdmin, selectIsFulfilmentTimeSlotsAvailable,
} from '../../redux/selectors'
import { openAddressModal, setDateModalOpen } from '../../redux/actions'
import FulfillmentMethod from './FulfillmentMethod'
import Time from './Time'
import AddressModal from '../AddressModal'
import DateModal from '../DateModal'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'

export default function FulfillmentDetails() {
  const cart = useSelector(selectCart)
  const fulfillmentMethod = useSelector(selectFulfilmentMethod)
  const fulfilmentTimeRange = useSelector(selectFulfilmentTimeRange)

  const isOrderAdmin = useSelector(selectIsOrderAdmin)
  const isFulfilmentTimeSlotsAvailable = useSelector(selectIsFulfilmentTimeSlotsAvailable)

  const canAddToExistingCart = useSelector(selectCanAddToExistingCart)

  const errors = useSelector(selectFulfillmentRelatedErrorMessages)

  const [isWarningModalOpen, setWarningModalOpen] = useState(false)
  const continueURL = window.Routing.generate('order_continue')

  const { t } = useTranslation()

  const dispatch = useDispatch()

  const changeFulfillmentMethod = () => {
    if (canAddToExistingCart) {
      dispatch(openAddressModal(cart.restaurant))
    } else {
      setWarningModalOpen(true)
    }
  }

  const changeTimeSlot = () => {
    if (canAddToExistingCart) {
      dispatch(setDateModalOpen(true))
    } else {
      setWarningModalOpen(true)
    }
  }

  return (
    <div className="panel panel-default">
      <div className="panel-body">
        <div className="fulfillment-details">
          <FulfillmentMethod
            value={ fulfillmentMethod }
            shippingAddress={ cart.shippingAddress }
            onClick={ changeFulfillmentMethod }
            allowEdit={ isOrderAdmin } />
          { isFulfilmentTimeSlotsAvailable ? (
            <Time
              timeRange={fulfilmentTimeRange}
              onClick={ changeTimeSlot }
              allowEdit={ isOrderAdmin } />) : t('NOT_AVAILABLE_ATM') }
          { errors.length > 0 ? (
            <div className="alert alert-warning">
              <i className="fa fa-warning"></i>
              &nbsp;
              <span>{ _.first(errors) }</span>
            </div>
          ) : null }
        </div>
      </div>
      { canAddToExistingCart ? (<AddressModal />) : null}
      { canAddToExistingCart ? (<DateModal />) : null}
      <Modal
        isOpen={ isWarningModalOpen }
        contentLabel={ t('CART_CHANGE_RESTAURANT_MODAL_LABEL') }
        className="ReactModal__Content--restaurant">
        <div>
          <div className="text-center">
            <p>
              { t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_TEXT_LINE_1') }
              <br />
              { t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_TEXT_LINE_2') }
            </p>
          </div>
          <div className="ReactModal__Restaurant__button">
            <a className="btn btn-default" href={ continueURL }>
              { t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_BTN_NO') }
            </a>
            <button type="button" className="btn btn-primary" onClick={ () => setWarningModalOpen(false) }>
              { t('CART_CHANGE_FULFILMENT_DETAILS_MODAL_BTN_YES') }
            </button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
