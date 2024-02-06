import React from 'react'
import {
  useProduct,
} from './ProductOptionsModalContext'

export default function ProductQuantity () {
  const { price, quantity, setQuantity } = useProduct()

  return (
    <div className="quantity-input-group">
      <span className="quantity-input-group__price">{(price /
        100).formatMoney()}</span>
      <button className="quantity-decrement" type="button"
              onClick={() => {
                if (quantity > 1) {
                  setQuantity(quantity - 1)
                }
              }}>
        <div>-</div>
      </button>
      <input type="number" min="1" step="1" value={quantity}
             data-product-quantity
             onChange={e => setQuantity(
               parseInt(e.currentTarget.value, 10))}/>
      <button className="quantity-increment" type="button"
              onClick={() => setQuantity(quantity + 1)}>
        <div>+</div>
      </button>
    </div>
  )
}
