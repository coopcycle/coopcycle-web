import React from 'react'
import CartItem from './CartItem'
import { createStoreFromPreloadedState } from '../../redux/store'
import { Provider } from 'react-redux'

export default {
  title: 'Foodtech/4. Order/3. Cart Item',
  tags: [ 'autodocs' ],
  component: CartItem,

  decorators: [
    (Story, context) => {
      const store = createStoreFromPreloadedState(context.args._store ?? {})

      return (
        <Provider store={ store }>
          {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */ }
          <Story />
        </Provider>
      )
    },
  ],
}

export const Basic = {
  args: {
    id: 1,
    name: 'Pizza',
    total: 1000,
    quantity: 1,
    adjustments: {},
  },
}

export const BasicLoading = {
  args: {
    id: 1,
    name: 'Pizza',
    total: 1000,
    quantity: 1,
    adjustments: {},
    _store: {
      isFetching: true,
    }
  },
}

export const LongName = {
  args: {
    id: 1,
    name: 'Long name pizza with extra cheese and pepperoni',
    total: 1000,
    quantity: 1,
    adjustments: {},
  },
}

export const WithAdjustmentsTaxExcluded = {
  args: {
    id: 1,
    name: 'Pizza',
    total: 1000,
    quantity: 1,
    adjustments: {
      menu_item_modifier: [
        { label: 'Extra cheese', amount: 100 },
        { label: 'Pepperoni', amount: 150 },
      ],
      tax: [
        { label: 'VAT', amount: 100 },
      ],
    },
    _store: {
      country: 'fr',
    }
  },
}

export const WithAdjustmentsTaxIncluded = {
  args: {
    id: 1,
    name: 'Pizza',
    quantity: 1,
    total: 1000,
    adjustments: {
      menu_item_modifier: [
        { label: 'Extra cheese', amount: 100 },
        { label: 'Pepperoni', amount: 150 },
      ],
      tax: [
        { label: 'VAT', amount: 100 },
      ],
    },
    _store: {
      country: 'ca',
    }
  },
}
