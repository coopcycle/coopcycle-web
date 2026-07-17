import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  persistedTimeRange: null,
  isModalOpen: false,
  isChangeModalOpen: false,
}

const slice = createSlice({
  name: 'timeRange',
  initialState,
  reducers: {
    setPersistedTimeRange: (state, action) => {
      state.persistedTimeRange = action.payload
    },
    openTimeRangeChangedModal: (state) => {
      state.isModalOpen = true
    },
    closeTimeRangeChangedModal: (state) => {
      state.isModalOpen = false
    },
    openChangeTimeRangeModal: (state) => {
      state.isChangeModalOpen = true
    },
    closeChangeTimeRangeModal: (state) => {
      state.isChangeModalOpen = false
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  setPersistedTimeRange,
  openTimeRangeChangedModal,
  closeTimeRangeChangedModal,
  openChangeTimeRangeModal,
  closeChangeTimeRangeModal,
} = slice.actions

export const timeRangeSlice = slice

export const selectPersistedTimeRange = state => state.timeRange.persistedTimeRange

export const selectIsTimeRangeChangedModalOpen = state => state.timeRange.isModalOpen

export const selectIsChangeTimeRangeModalOpen = state => state.timeRange.isChangeModalOpen
