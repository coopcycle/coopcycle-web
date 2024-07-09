import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  recurrenceRule: null,
  modalIsOpen: false,
}

const slice = createSlice({
  name: 'recurrenceRules',
  initialState,
  reducers: {
    createRecurrenceRule: (state, action) => {
      state.recurrenceRule = action.payload
    },
    updateRecurrenceRule: (state, action) => {
      state.recurrenceRule = action.payload
    },
    deleteRecurrenceRule: state => {
      state.recurrenceRule = null
    },
    openNewRecurrenceRuleModal: state => {
      state.modalIsOpen = true
    },
    closeRecurrenceRuleModal: state => {
      state.modalIsOpen = false
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  createRecurrenceRule,
  updateRecurrenceRule,
  deleteRecurrenceRule,
  openNewRecurrenceRuleModal,
  closeRecurrenceRuleModal,
} = slice.actions

export const recurrenceRulesSlice = slice

export const selectRecurrenceRule = state =>
  state.recurrenceRules.recurrenceRule
export const selectIsRecurrenceRuleModalOpen = state =>
  state.recurrenceRules.modalIsOpen
