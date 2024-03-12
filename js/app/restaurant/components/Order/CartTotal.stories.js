import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../redux/store'
import CartTotal from './CartTotal'

export default {
  title: 'Foodtech/4. Order/4. Cart Total',
  tags: [ 'autodocs' ],
  component: CartTotal,

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
        itemsTotal: 2000,
        total: 2100,
        adjustments: {
          delivery: [
            {
              id: 1,
              label: 'Delivery',
              amount: 100,
            },
          ],
          tax: [
            {
              id: 1,
              label: 'VAT',
              amount: 200,
            },
          ],
        },
      },
      isPlayer: false,
      player: {
        player: null,
      },
    },
  },
}
