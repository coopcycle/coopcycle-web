import { createSlice } from '@reduxjs/toolkit'

// structure is based on the Order entity
const initialState = {
  '@id': null,
  shippingTimeRange: null,
  events: [],
}

const slice = createSlice({
  name: 'order',
  initialState,
  reducers: {
    setOrderNodeId: (state, action) => {
      state['@id'] = action.payload
    },
    setShippingTimeRange: (state, action) => {
      state.shippingTimeRange = action.payload
    },
    setOrderEvents: (state, action) => {
      state.events = action.payload
    },
    addOrderEvent: (state, action) => {
      state.events.push(action.payload)
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  setOrderNodeId,
  setShippingTimeRange,
  setOrderEvents,
  addOrderEvent,
} = slice.actions

export const orderSlice = slice

export const selectOrderNodeId = state => state.order['@id']
export const selectShippingTimeRange = state => state.order.shippingTimeRange

export const selectOrderEvents = state => state.order.events
