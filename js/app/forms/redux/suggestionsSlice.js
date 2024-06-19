import { createSlice } from '@reduxjs/toolkit'

const initialState = {
  showSuggestions: false,
  suggestions: [],
}

const slice = createSlice({
  name: 'suggestions',
  initialState,
  reducers: {
    showSuggestions: (state, action) => {
      state.showSuggestions = true
      state.suggestions = action.payload
    },
    rejectSuggestions: (state) => {
      state.showSuggestions = false
      state.suggestions = []
    },
    acceptSuggestions: (state) => {
      state.showSuggestions = false
      state.suggestions = []
    },
  },
})

// Action creators are generated for each case reducer function
export const {
  showSuggestions,
  rejectSuggestions,
  acceptSuggestions,
} = slice.actions

export const suggestionsSlice = slice
