import React from 'react'
import ProductModalHeader from './ProductModalHeader'

export default {
  title: 'Foodtech/3. Product Details/2. Header',
  tags: ['autodocs'],
  component: ProductModalHeader,
  decorators: [
    (Story) => (
      <div className="ReactModal__Content--product-options">
        <div className="product-modal-container">
          {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */}
          <Story/>
        </div>
      </div>
    ),
  ],
}

export const Primary = {
  args: {
    name: 'Pizza',
  },
}

export const LongName = {
  args: {
    name: 'DOC ORVIETO CLASSICO "CAMPOGRANDE" - SANTA CRISTINA Cépage "Procanico, Grechetto, Verdello, Druppeggio, Malvasia"',
  },
}
