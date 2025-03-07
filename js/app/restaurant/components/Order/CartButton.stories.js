import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../redux/store'
import CartButton from './CartButton'

export default {
  title: 'Foodtech/4. Order/5. Cart Button',
  tags: [ 'autodocs' ],
  component: CartButton,

  decorators: [
    (Story, context) => {
      const store = createStoreFromPreloadedState(context.args._store)

      return (
        <Provider store={ store }>
          <div className="cart__footer">
            {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */ }
            <Story />
          </div>
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
        itemsTotal: 2000,
        total: 2100,
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
    },
  },
}

export const Loading = {
  args: {
    _store: {
      isFetching: true,
      cart: {
        items: [
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
        itemsTotal: 2000,
        total: 2100,
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
    },
  },
}

export const Error = {
  args: {
    _store: {
      isFetching: false,
      errors: {
        'shippingAddress': [
          {
            message: 'Wrong address',
          },
        ],
      },
      cart: {
        items: [
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
        itemsTotal: 2000,
        total: 2100,
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
    },
  },
}
