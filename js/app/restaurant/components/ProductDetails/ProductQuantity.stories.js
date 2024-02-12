import React from 'react'
import ProductQuantity from './ProductQuantity'
import { ProductOptionsModalProvider } from './ProductOptionsModalContext'

export default {
  title: 'Foodtech/3. Product Details/4. Quantity',
  tags: ['autodocs'],
  component: ProductQuantity,

  decorators: [
    (Story, context) => (
      <div className="ReactModal__Content--product-options">
        <ProductOptionsModalProvider
          options={context.args.options}
          price={context.args.price}>

          <div className="product-modal-container">
            {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */}
            <Story/>
          </div>

        </ProductOptionsModalProvider>
      </div>
    ),
  ],
}

export const Primary = {
  args: {
    name: 'Pizza',
    options: [],
    price: 1000,
  },
}
