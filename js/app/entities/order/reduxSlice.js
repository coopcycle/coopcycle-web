import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  '@id': null,
  shippingTimeRange: null,
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
  },
})

// Action creators are generated for each case reducer function
export const { setOrderNodeId, setShippingTimeRange } = slice.actions

export const orderSlice = slice

export const selectOrderNodeId = state => state.order['@id']
export const selectShippingTimeRange = state => state.order.shippingTimeRange
