import { createSlice } from '@reduxjs/toolkit'

const initialState = {}

const slice = createSlice({
  name: 'store',
  initialState,
  reducers: {},
})

// Action creators are generated for each case reducer function
// export const { setOrderAccessToken } = slice.actions

export const storeSlice = slice

export const selectStoreUri = state => state.store
