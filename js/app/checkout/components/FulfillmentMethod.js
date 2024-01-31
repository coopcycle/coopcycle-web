import React from 'react'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'

const Icon = ({ type }) => (
  <span className="mr-2">
    <i className={ classNames({
      'fa': true,
      'fa-bicycle': type === 'delivery',
      'fa-cube': type === 'collection',
    }) }></i>
  </span>
)

const FulfillmentMethod = ({ value, shippingAddress, onClick, allowEdit }) => {

  const { t } = useTranslation()

  return (
    <a href="#"
      className="d-flex align-items-start justify-content-between border-bottom pb-4 text-decoration-none"
      onClick={ e => {
        e.preventDefault()
        if (allowEdit) {
          onClick()
        }
      }}>
      <span>
        { value === 'collection' && (
          <React.Fragment>
            <Icon type={ value } />
            <strong>{ t('CART_TAKE_AWAY') }</strong>
          </React.Fragment>
        ) }
        { value === 'delivery' && (
          <React.Fragment>
            <span>
              <Icon type={ value } />
              <strong>{ t('RULE_PICKER_LINE_OPTGROUP_DELIVERY') }</strong>
            </span>
            <br />
            <small className="text-muted" data-testid="cart.shippingAddress">{ shippingAddress?.streetAddress }</small>
          </React.Fragment>
        ) }
      </span>
      { allowEdit &&
      <span className="pl-4">
        <small>{ t('CART_DELIVERY_TIME_EDIT') }</small>
      </span> }
    </a>
  )
}

export default FulfillmentMethod
