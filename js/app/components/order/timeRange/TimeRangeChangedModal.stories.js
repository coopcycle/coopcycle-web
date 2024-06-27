import React from 'react'
import { Provider } from 'react-redux'
import TimeRangeChangedModal from './TimeRangeChangedModal'
import { configureStore } from '@reduxjs/toolkit'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { guestSlice } from '../../../entities/guest/reduxSlice'
import { orderSlice } from '../../../entities/order/reduxSlice'
import { timeRangeSlice } from './reduxSlice'
import { apiSlice } from '../../../api/slice'

export default {
  title: 'Foodtech/4. Order/9. TimeRangeChangedModal',
  tags: ['autodocs'],
  component: TimeRangeChangedModal,

  decorators: [
    (Story, context) => {
      const store = configureStore({
        reducer: {
          [accountSlice.name]: accountSlice.reducer,
          [guestSlice.name]: guestSlice.reducer,
          [orderSlice.name]: orderSlice.reducer,
          [timeRangeSlice.name]: timeRangeSlice.reducer,
          [apiSlice.reducerPath]: apiSlice.reducer,
        },
        preloadedState: context.args._store,
      })

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
      [orderSlice.name]: {
        '@id': '/api/orders/1055',
      },
      [timeRangeSlice.name]: {
        persistedTimeRange: {
          start: '2021-10-01T10:00:00+02:00',
          end: '2021-10-01T11:00:00+02:00',
        },
        isModalOpen: true,
      },
      [apiSlice.reducerPath]: {
        queries: {
          'getOrderTiming("/api/orders/1055")': {
            status: 'fulfilled',
            endpointName: 'getOrderTiming',
            requestId: 'PfMTmYHnxWm0phwFT0x8T',
            originalArgs: '/api/orders/1055',
            startedTimeStamp: 1719349684810,
            data: {
              behavior: 'asap',
              preparation: '15 minutes',
              shipping: '1 minute 59 seconds',
              asap: '2024-06-25T14:45:00-07:00',
              range: ['2024-06-25T14:40:00-07:00', '2024-06-25T14:50:00-07:00'],
              today: true,
              fast: true,
              diff: '35 - 45',
              ranges: [
                ['2024-06-25T14:40:00-07:00', '2024-06-25T14:50:00-07:00'],
                ['2024-06-25T14:50:00-07:00', '2024-06-25T15:00:00-07:00'],
                ['2024-06-25T15:00:00-07:00', '2024-06-25T15:10:00-07:00'],
              ],
              choices: [
                '2024-06-25T14:45:00-07:00',
                '2024-06-25T14:55:00-07:00',
                '2024-06-25T15:05:00-07:00',
              ],
            },
            fulfilledTimeStamp: 1719349686519,
          },
        },
      },
    },
  },
}

export const IsLoading = {
  args: {
    _store: {
      [orderSlice.name]: {
        '@id': '/api/orders/1055',
      },
      [timeRangeSlice.name]: {
        persistedTimeRange: {
          start: '2021-10-01T10:00:00+02:00',
          end: '2021-10-01T11:00:00+02:00',
        },
        isModalOpen: true,
      },
      [apiSlice.reducerPath]: {
        queries: {
          'getOrderTiming("/api/orders/1055")': {
            status: 'pending',
            endpointName: 'getOrderTiming',
            requestId: 'PfMTmYHnxWm0phwFT0x8T',
            originalArgs: '/api/orders/1055',
            startedTimeStamp: 1719349684810,
          },
        },
      },
    },
  },
}

export const NoTimeRangesAvailable = {
  args: {
    _store: {
      [timeRangeSlice.name]: {
        persistedTimeRange: {
          start: '2021-10-01T10:00:00+02:00',
          end: '2021-10-01T11:00:00+02:00',
        },
        isModalOpen: true,
      },
    },
  },
}
