import React from 'react'
import { Provider } from 'react-redux'
import ChangeRestaurantOnEditFulfilmentDetailsModal from './ChangeRestaurantOnEditFulfilmentDetailsModal'
import { createStoreFromPreloadedState } from '../../redux/store'

export default {
  title: 'Foodtech/4. Order/8. ChangeRestaurantOnEditFulfilmentDetailsModal',
  tags: ['autodocs'],
  component: ChangeRestaurantOnEditFulfilmentDetailsModal,

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
    isWarningModalOpen: true,
    _store: {},
  },
}
