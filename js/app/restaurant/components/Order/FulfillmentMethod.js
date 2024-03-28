import React from 'react'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'

const Icon = ({ type }) => (
  <span className="mr-2">
    <i className={ classNames({
      'fa': true,
      'fa-bicycle': type === 'delivery',
      'fa-cube': type === 'collection',
    }) } />
  </span>)

export default function FulfillmentMethod({ value, shippingAddress, onClick, allowEdit }) {

  const { t } = useTranslation()

  return (
    <div className="d-flex justify-content-between">
      <span>
        { value === 'collection' && (
          <React.Fragment>
            <Icon type={ value } />
            <strong>{ t('CART_TAKE_AWAY') }</strong>
          </React.Fragment>) }
        { value === 'delivery' && (
          <React.Fragment>
            <span>
              <Icon type={ value } />
              <strong>{ t('RULE_PICKER_LINE_OPTGROUP_DELIVERY') }</strong>
            </span>
            <br />
            <span
              data-testid="cart.shippingAddress">{ shippingAddress?.streetAddress }</span>
          </React.Fragment>) }
      </span>
      { allowEdit ? (
        <a
          href="#"
          className="pl-3 text-decoration-none"
          onClick={ e => {
            e.preventDefault()
            onClick()
          } }>
          <span>{ t('CART_DELIVERY_TIME_EDIT') }</span>
        </a>) : null }
    </div>)
}
