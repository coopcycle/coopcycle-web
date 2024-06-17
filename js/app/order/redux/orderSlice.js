import { createSlice } from '@reduxjs/toolkit'

export const initialState = {
  '@id': null,
  shippingTimeRange: null,
  persistedTimeRange: null,
}

export const orderSlice = createSlice({
  name: 'order',
  initialState,
  reducers: {
    setShippingTimeRange: (state, action) => {
      state.shippingTimeRange = action.payload
    },
    setPersistedTimeRange: (state, action) => {
      state.persistedTimeRange = action.payload
    },
  },
})

// Action creators are generated for each case reducer function
export const { setShippingTimeRange, setPersistedTimeRange } = orderSlice.actions

export default orderSlice.reducer
