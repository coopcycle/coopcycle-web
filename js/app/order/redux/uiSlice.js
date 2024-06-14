import { createSlice } from '@reduxjs/toolkit'

export const initialState = {
  isTimeRangeChangedModalOpen: false,
}

export const uiSlice = createSlice({
  name: 'ui',
  initialState,
  reducers: {
    openTimeRangeChangedModal: (state) => {
      state.isTimeRangeChangedModalOpen = true
    },
    closeTimeRangeChangedModal: (state) => {
      state.isTimeRangeChangedModalOpen = false
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  openTimeRangeChangedModal,
  closeTimeRangeChangedModal,
} = uiSlice.actions

export default uiSlice.reducer
