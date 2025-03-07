import React from 'react'
import { useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import _ from 'lodash'

import CartItem from './CartItem'
import {
  GROUP_ORDER_ADMIN,
  selectIsGroupOrderAdmin,
  selectItems,
  selectItemsGroups,
  selectPlayersGroups,
} from '../../redux/selectors'

import cartEmptySvg from '../../../../../assets/svg/cart_empty.svg'

function ListOfItems({ items }) {
  // Make sure items are always in the same order
  // We order them by id asc
  items.sort((a, b) => a.id - b.id)

  return (
    <div className="cart__items">
      { items.map((item) => (
        <CartItem
          key={ `cart-item-${ item.id }` }
          id={ item.id }
          name={ item.name }
          total={ item.total }
          quantity={ item.quantity }
          adjustments={ item.adjustments } />
      )) }
    </div>
  )
}

export default function Cart() {
  const items = useSelector(selectItems)
  const itemsGroups = useSelector(selectItemsGroups)
  const isGroupOrderAdmin = useSelector(selectIsGroupOrderAdmin)
  const playersGroups = useSelector(selectPlayersGroups)

  const { t } = useTranslation()

  if (items.length === 0) {
    return (
      <div className="cart__empty" data-testid="cart.empty">
        <img src={ cartEmptySvg } />
        <div className="alert alert-info w-100 mt-3">
          <i className="fa fa-info-circle"></i>&nbsp;{ t('CART_EMPTY') }
        </div>
      </div>
    )
  }

  if (_.size(itemsGroups) > 1) {

    return (
      <div className="hub-order">
        { _.map(itemsGroups, (items, title) => {
          return (
            <div key={ `cart-restaurant-${ title }` }>
              <h5 className="text-muted">{ title }</h5>
              <ListOfItems items={ items } />
            </div>
          )
        }) }
      </div>
    )
  }

  if (isGroupOrderAdmin) {
    return (
      <div className="group-order">
        { _.map(playersGroups, (item, username) => (
          <div key={ `cart-player-${ username }` }>
            <div className="username">
              { (username === GROUP_ORDER_ADMIN)
                ? t('GROUP_ORDER_YOU')
                : username }
            </div>
            <ListOfItems items={ playersGroups[username] } />
          </div>
        )) }
      </div>
    )
  }

  return (
    <ListOfItems items={ items } />
  )
}
