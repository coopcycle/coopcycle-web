import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../redux/store'
import FulfillmentDetails from './FulfillmentDetails'

export default {
  title: 'Foodtech/4. Order/6. Fulfillment Details',
  tags: [ 'autodocs' ],
  component: FulfillmentDetails,

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

export const Delivery = {
  args: {
    _store: {
      cart: {
        vendor: {},
        shippingAddress: {
          streetAddress: '123 Main Street',
        },
        items: [],
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
      cartTiming: {
        today: true,
        range: [
          '2021-09-29T12:00:00+01:00',
          '2021-09-29T14:00:00+01:00',
        ],
      },
    },
  },
}

export const DeliveryNoAddress = {
  args: {
    _store: {
      errors: {
        'shippingAddress': [
          {
            message: 'Please enter the delivery address',
          },
        ],
      },
      cart: {
        vendor: {},
        items: [],
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
      cartTiming: {
        today: true,
        range: [
          '2021-09-29T12:00:00+01:00',
          '2021-09-29T14:00:00+01:00',
        ],
      },
    },
  },
}

export const Takeaway = {
  args: {
    _store: {
      cart: {
        vendor: {},
        takeaway: true,
        items: [],
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
      cartTiming: {
        today: true,
        range: [
          '2021-09-29T12:00:00+01:00',
          '2021-09-29T14:00:00+01:00',
        ],
      },
    },
  },
}

export const NotAvailable = {
  args: {
    _store: {
      errors: {
        'shippingTimeRange': [
          {
            'message': 'Not available at the moment',
            'code': 'Order::SHIPPING_TIME_RANGE_NOT_AVAILABLE',
          },
        ],
      },
      cart: {
        vendor: {},
        items: [],
      },
      isPlayer: false,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
      cartTiming: {
        today: true,
        range: [
          '2021-09-29T12:00:00+01:00',
          '2021-09-29T14:00:00+01:00',
        ],
      },
    },
  },
}

export const IsPlayer = {
  args: {
    _store: {
      errors: {
        'shippingTimeRange': [
          {
            'message': 'Not available at the moment',
            'code': 'Order::SHIPPING_TIME_RANGE_NOT_AVAILABLE',
          },
        ],
      },
      cart: {
        vendor: {},
        items: [],
      },
      isPlayer: true,
      player: {
        player: null,
      },
      restaurant: {
        isOpen: true,
      },
      cartTiming: {
        today: true,
        range: [
          '2021-09-29T12:00:00+01:00',
          '2021-09-29T14:00:00+01:00',
        ],
      },
    },
  },
}
