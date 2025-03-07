import React from 'react'
import ProductInfo from './ProductInfo'

export default {
  title: 'Foodtech/3. Product Details/3. Info',
  tags: [ 'autodocs' ],
  component: ProductInfo,
  decorators: [
    (Story) => (
      <div className="ReactModal__Content--product-options">
        {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */ }
        <Story />
      </div>
    ),
  ],
}

export const Primary = {
  args: {
    product: {
      description: 'Mozzarella (fior di latte), parmesan 24 mois, gorgonzola, taleggio (fromage de vache), mascarpone, origan.',
      allergens: [ 'CEREALS_CONTAINING_GLUTEN', 'MILK' ],
      suitableForDiet: [ 'http:\\/\\/schema.org\\/VegetarianDiet' ],
    },
  },
}
