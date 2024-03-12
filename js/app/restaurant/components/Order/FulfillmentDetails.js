import React from 'react'
import { useDispatch, useSelector } from 'react-redux'
import _ from 'lodash'

import {
  selectCart,
  selectFulfillmentRelatedErrorMessages,
  selectIsCollectionEnabled,
  selectIsDeliveryEnabled,
  selectIsOrderingAvailable,
  selectIsPlayer,
} from '../../redux/selectors'
import { openAddressModal } from '../../redux/actions'
import FulfillmentMethod from './FulfillmentMethod'
import Time from './Time'
import AddressModal from '../AddressModal'
import DateModal from '../DateModal'

export default function FulfillmentDetails() {
  const isCollectionEnabled = useSelector(selectIsCollectionEnabled)
  const isDeliveryEnabled = useSelector(selectIsDeliveryEnabled)

  const isPlayer = useSelector(selectIsPlayer)
  const isOrderingAvailable = useSelector(selectIsOrderingAvailable)

  const cart = useSelector(selectCart)
  const fulfillmentMethod = (cart.takeaway ||
    (isCollectionEnabled && !isDeliveryEnabled)) ? 'collection' : 'delivery'

  const errors = useSelector(selectFulfillmentRelatedErrorMessages)

  const dispatch = useDispatch()

  return (
    <div className="panel panel-default">
      <div className="panel-body">
        <div className="fulfillment-details">
          <FulfillmentMethod
            value={ fulfillmentMethod }
            shippingAddress={ cart.shippingAddress }
            onClick={ () => dispatch(openAddressModal(cart.restaurant)) }
            allowEdit={ !isPlayer } />
          { isOrderingAvailable && <Time /> }
          { errors.length > 0 ? (
            <div className="alert alert-warning">
              <i className="fa fa-warning"></i>
              &nbsp;
              <span>{ _.first(errors) }</span>
            </div>
          ) : null }
        </div>
      </div>
      <AddressModal />
      <DateModal />
    </div>
  )
}
