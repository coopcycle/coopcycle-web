import { createSlice } from '@reduxjs/toolkit'

export const initialState = {
  '@id': null,
}

export const orderSlice = createSlice({
  name: 'order',
  initialState,
  reducers: {},
})

// Action creators are generated for each case reducer function
// export const { increment, decrement, incrementByAmount } = orderSlice.actions

export default orderSlice.reducer
