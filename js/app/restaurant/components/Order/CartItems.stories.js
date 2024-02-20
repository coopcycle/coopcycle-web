import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../redux/store'
import CartItems from './CartItems'

export default {
  title: 'Foodtech/4. Order/1. Cart Items',
  tags: [ 'autodocs' ],
  component: CartItems,

  decorators: [
    (Story, context) => {
      const store = createStoreFromPreloadedState(context.args._store)

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
    _store: {
      cart: {
        items: [
          {
            id: 1,
            name: 'Pizza Margherita',
            total: 1000,
            quantity: 1,
            adjustments: {
              menu_item_modifier: [
                { label: 'Extra cheese' },
                { label: 'Pepperoni', amount: 150 },
              ],
              tax: [
                { label: 'VAT', amount: 100 },
              ],
            },
            vendor: {
              name: 'Pizza Hut',
            },
            player: null,
          },
          {
            id: 2,
            name: 'Pizza Pepperoni',
            total: 1000,
            quantity: 1,
            adjustments: {},
            vendor: {
              name: 'Pizza Hut',
            },
            player: null,
          },
        ],
      },
      isPlayer: false,
      player: {
        player: null,
      },
    },
  },
}

export const EmptyState = {
  args: {
    _store: {
      cart: {
        items: [],
      },
      isPlayer: false,
      player: {
        player: null,
      },
    },
  },
}

export const GroupOrder = {
  args: {
    _store: {
      cart: {
        items: [
          {
            id: 1,
            name: 'Pizza Margherita',
            total: 1000,
            quantity: 1,
            adjustments: {
              menu_item_modifier: [
                { label: 'Extra cheese' },
                { label: 'Pepperoni', amount: 150 },
              ],
              tax: [
                { label: 'VAT', amount: 100 },
              ],
            },
            vendor: {
              name: 'Pizza Hut',
            },
            player: null,
          },
          {
            id: 2,
            name: 'Pizza Pepperoni',
            total: 1000,
            quantity: 1,
            adjustments: {},
            vendor: {
              name: 'Pizza Hut',
            },
            player: {
              username: 'John Doe',
            },
          },
        ],
      },
      isPlayer: false,
      player: {
        player: null,
      },
    },
  },
}

export const HubOrder = {
  args: {
    _store: {
      cart: {
        items: [
          {
            id: 1,
            name: 'Pizza Margherita',
            total: 1000,
            quantity: 1,
            adjustments: {
              menu_item_modifier: [
                { label: 'Extra cheese' },
                { label: 'Pepperoni', amount: 150 },
              ],
              tax: [
                { label: 'VAT', amount: 100 },
              ],
            },
            vendor: {
              name: 'Pizza Hut',
            },
            player: null,
          },
          {
            id: 2,
            name: 'Pizza Pepperoni',
            total: 1000,
            quantity: 1,
            adjustments: {},
            vendor: {
              name: 'Pizza Hut',
            },
            player: null,
          },
          {
            id: 3,
            name: 'Sushi Box',
            total: 1000,
            quantity: 1,
            adjustments: {},
            vendor: {
              name: 'Sushi Restaurant',
            },
            player: null,
          },
        ],
      },
      isPlayer: false,
      player: {
        player: null,
      },
    },
  },
}
