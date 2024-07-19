import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  rule: null,
  isCancelled: false,
  modalIsOpen: false,
}

const slice = createSlice({
  name: 'recurrence',
  initialState,
  reducers: {
    createRecurrenceRule: (state, action) => {
      state.rule = action.payload
    },
    updateRecurrenceRule: (state, action) => {
      state.rule = action.payload
    },
    deleteRecurrenceRule: state => {
      state.rule = null
    },
    openRecurrenceModal: state => {
      state.modalIsOpen = true
    },
    closeRecurrenceModal: state => {
      state.modalIsOpen = false
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  createRecurrenceRule,
  updateRecurrenceRule,
  deleteRecurrenceRule,
  openRecurrenceModal,
  closeRecurrenceModal,
} = slice.actions

export const recurrenceSlice = slice

export const selectRecurrenceRule = state =>
  state.recurrence.rule
export const selectIsCancelled = state =>
  state.recurrence.isCancelled
export const selectIsRecurrenceModalOpen = state =>
  state.recurrence.modalIsOpen
