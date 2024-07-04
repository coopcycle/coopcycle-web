import React, { useState } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import _ from 'lodash'

import {
  selectCart,
  selectFulfilmentMethod,
  selectFulfillmentRelatedErrorMessages,
  selectFulfilmentTimeRange,
  selectCanAddToExistingCart,
  selectIsOrderAdmin,
  selectIsFulfilmentTimeSlotsAvailable,
} from '../../redux/selectors'
import {
  openAddressModal, setDateModalOpen,
} from '../../redux/actions'
import FulfillmentMethod from './FulfillmentMethod'
import Time from './Time'
import AddressModal from '../AddressModal'
import DateModal from '../DateModal'
import { useTranslation } from 'react-i18next'
import ChangeRestaurantOnEditFulfilmentDetailsModal
  from './ChangeRestaurantOnEditFulfilmentDetailsModal'
import TimeRangeChangedModal
  from '../../../components/order/timeRange/TimeRangeChangedModal'

export default function FulfillmentDetails() {
  const cart = useSelector(selectCart)
  const fulfillmentMethod = useSelector(selectFulfilmentMethod)
  const fulfilmentTimeRange = useSelector(selectFulfilmentTimeRange)

  const isOrderAdmin = useSelector(selectIsOrderAdmin)
  const isFulfilmentTimeSlotsAvailable = useSelector(
    selectIsFulfilmentTimeSlotsAvailable)

  const canAddToExistingCart = useSelector(selectCanAddToExistingCart)

  const errors = useSelector(selectFulfillmentRelatedErrorMessages)

  const [ isWarningModalOpen, setWarningModalOpen ] = useState(false)

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

  return (<div className="panel panel-default">
      <div className="panel-body">
        <div className="fulfillment-details"
             data-testid="cart.fulfillmentDetails">
          <FulfillmentMethod
            value={ fulfillmentMethod }
            shippingAddress={ cart.shippingAddress }
            onClick={ changeFulfillmentMethod }
            allowEdit={ isOrderAdmin } />
          { isFulfilmentTimeSlotsAvailable ? (<Time
            timeRange={ fulfilmentTimeRange }
            onClick={ changeTimeSlot }
            allowEdit={ isOrderAdmin } />) : t('NOT_AVAILABLE_ATM') }
          { errors.length > 0 ? (<div className="alert alert-warning">
              <i className="fa fa-warning"></i>
              &nbsp;
              <span>{ _.first(errors) }</span>
            </div>) : null }
        </div>
      </div>
      { canAddToExistingCart ? (<AddressModal />) : null }
      { canAddToExistingCart ? (<DateModal />) : null }
      <ChangeRestaurantOnEditFulfilmentDetailsModal
        isWarningModalOpen={ isWarningModalOpen }
        setWarningModalOpen={ setWarningModalOpen } />
      <TimeRangeChangedModal />
    </div>)
}
