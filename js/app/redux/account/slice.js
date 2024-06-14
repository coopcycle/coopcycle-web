import { createSlice } from '@reduxjs/toolkit'

export const initialState = {
  accessToken: null,
}

// only for registered users, for guest users (guest checkout) use guestSlice
export const slice = createSlice({
  name: 'account',
  initialState,
  reducers: {},
})

// Action creators are generated for each case reducer function
// export const { increment, decrement, incrementByAmount } = slice.actions

export default slice.reducer
