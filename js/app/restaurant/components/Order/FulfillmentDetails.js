import React from 'react'
import classNames from 'classnames'
import FulfillmentMethod from './FulfillmentMethod'
import Time from './Time'
import AddressModal from '../AddressModal'
import DateModal from '../DateModal'
import { useDispatch, useSelector } from 'react-redux'
import {
  selectCart,
  selectIsCollectionEnabled,
  selectIsDeliveryEnabled, selectIsOrderingAvailable, selectIsPlayer,
} from '../../redux/selectors'
import { openAddressModal } from '../../redux/actions'

export default function FulfillmentDetails() {
  const isCollectionEnabled = useSelector(selectIsCollectionEnabled)
  const isDeliveryEnabled = useSelector(selectIsDeliveryEnabled)

  const isPlayer = useSelector(selectIsPlayer)
  const isOrderingAvailable = useSelector(selectIsOrderingAvailable)

  const cart = useSelector(selectCart)
  const fulfillmentMethod = (cart.takeaway ||
    (isCollectionEnabled && !isDeliveryEnabled)) ? 'collection' : 'delivery'

  const dispatch = useDispatch()

  return (
    <div className={ classNames({
      'panel': true,
      'panel-default': true,
    }) }>
      <div className="panel-body">
        <div className="fulfillment-details">
          <FulfillmentMethod
            value={ fulfillmentMethod }
            shippingAddress={ cart.shippingAddress }
            onClick={ () => dispatch(openAddressModal(cart.restaurant)) }
            allowEdit={ !isPlayer } />
          { isOrderingAvailable && <Time /> }
        </div>
      </div>
      <AddressModal />
      <DateModal />
    </div>
  )
}
