import React from 'react'
import {
  useProduct,
} from './useProduct'
import {
  DecrementQuantityButton,
  IncrementQuantityButton,
} from '../ChangeQuantityButton'

export default function ProductQuantity() {
  const {
    price,
    quantity,
    setQuantity,
    incrementQuantity,
    decrementQuantity,
  } = useProduct()

  return (
    <div className="quantity-input-group">
      <span className="quantity-input-group__price">
        { (price / 100).formatMoney() }
      </span>
      <DecrementQuantityButton onClick={ decrementQuantity } />
      <input
        type="number"
        min="1"
        step="1"
        value={ quantity }
        data-product-quantity
        onChange={ e => setQuantity(e.currentTarget.value) } />
      <IncrementQuantityButton onClick={ incrementQuantity } />
    </div>
  )
}
