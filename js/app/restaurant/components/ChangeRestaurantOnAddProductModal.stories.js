import React from 'react'
import { Provider } from 'react-redux'
import ChangeRestaurantOnAddProductModal from './ChangeRestaurantOnAddProductModal'
import { createStoreFromPreloadedState } from '../redux/store'

export default {
  title: 'Foodtech/4. Order/7. ChangeRestaurantOnAddProductModal',
  tags: ['autodocs'],
  component: ChangeRestaurantOnAddProductModal,

  decorators: [
    (Story, context) => {
      const store = createStoreFromPreloadedState(context.args._store)

      return (
        <Provider store={store}>
          {/* 👇 Decorators in Storybook also accept a function. Replace <Story/> with Story() to enable it  */}
          <Story />
        </Provider>
      )
    },
  ],
}

export const Basic = {
  args: {
    _store: {
      errors: {
        restaurant: 'error',
      },
    },
  },
}
