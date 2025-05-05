import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  zones: [],
  packages: [],
}

// only for registered users, for guest users (guest checkout) use guestSlice
const slice = createSlice({
  name: 'pricing',
  initialState,
  // reducers: {
  //   setAccessToken: (state, action) => {
  //     state.accessToken = action.payload
  //   },
  // },
})

// Action creators are generated for each case reducer function
// export const { setAccessToken } = slice.actions

export const pricingSlice = slice

export const selectZones = state => state.pricing.zones
export const selectPackages = state => state.pricing.packages
