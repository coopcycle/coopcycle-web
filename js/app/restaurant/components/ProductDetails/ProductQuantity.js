import React from 'react'
import {
  useProduct,
} from './useProduct'

export default function ProductQuantity () {
  const { price, quantity, setQuantity, incrementQuantity, decrementQuantity } = useProduct()

  return (
    <div className="quantity-input-group">
      <span className="quantity-input-group__price">{(price / 100).formatMoney()}</span>
      <button className="quantity-decrement" type="button"
              onClick={decrementQuantity}>
        <div>-</div>
      </button>
      <input type="number" min="1" step="1" value={quantity}
             data-product-quantity
             onChange={e => setQuantity(e.currentTarget.value)}/>
      <button className="quantity-increment" type="button"
              onClick={incrementQuantity}>
        <div>+</div>
      </button>
    </div>
  )
}
