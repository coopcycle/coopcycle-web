import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  accessToken: window._auth?.jwt,
}

// only for registered users, for guest users (guest checkout) use guestSlice
const slice = createSlice({
  name: 'account',
  initialState,
  reducers: {
    setAccessToken: (state, action) => {
      state.accessToken = action.payload
    },
  },
})

// Action creators are generated for each case reducer function
export const { setAccessToken } = slice.actions

export const accountSlice = slice

export const selectAccessToken = state => state.account.accessToken
