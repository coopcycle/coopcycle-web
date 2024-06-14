import { createSlice } from '@reduxjs/toolkit'

export const initialState = {
  orderAccessTokens: {},
}

export const slice = createSlice({
  name: 'guest',
  initialState,
  reducers: {
    setOrderAccessToken(state, action) {
      const { orderNodeId, orderAccessToken } = action.payload
      state.orderAccessTokens[orderNodeId] = orderAccessToken
    },
  },
})

// Action creators are generated for each case reducer function
export const { setOrderAccessToken } = slice.actions

export default slice.reducer
