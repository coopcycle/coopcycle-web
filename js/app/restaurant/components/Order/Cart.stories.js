import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../redux/store'
import Cart from './Cart'

export default {
  title: 'Foodtech/4. Order/2. Cart',
  tags: [ 'autodocs' ],
  component: Cart,

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

export const GroupOrderAdmin = {
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

export const GroupOrderPlayer = {
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
              '@id': '/api/users/1',
              username: 'John Doe',
            },
          },
        ],
      },
      isPlayer: true,
      player: {
        player: '/api/users/1',
      }
    },
  },
}

export const GroupOrderFromHubAdmin = {
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
            player: {
              '@id': '/api/users/1',
              username: 'John Doe',
            },
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
            player: {
              '@id': '/api/users/1',
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

export const GroupOrderFromHubPlayer = {
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
      isPlayer: true,
      player: {
        player: '/api/users/1',
      }
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
