import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  orderAccessTokens: {},
}

const slice = createSlice({
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

export const guestSlice = slice

export const selectOrderAccessTokens = state => state.guest.orderAccessTokens
