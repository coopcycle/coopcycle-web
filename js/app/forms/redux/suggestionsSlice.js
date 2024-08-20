import { createSlice } from '@reduxjs/toolkit'
import { createSelector } from 'reselect'

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

export const selectSuggestions = state =>
  state.suggestions.suggestions

const selectSuggestedOrder = createSelector(
  selectSuggestions,
  (suggestions) => suggestions.length > 0 ? suggestions[0].order : []
)

export const selectSuggestedGain = createSelector(
  selectSuggestions,
  (suggestions) => suggestions.length > 0 ? suggestions[0].gain : { amount: 0 }
)

export const selectSuggestedTasks = createSelector(
  selectSuggestedOrder,
  state => state.tasks,
  (suggestedOrder, tasks) => {
    const suggestedTasks = []
    suggestedOrder.forEach((oldIndex, newIndex) => {
      suggestedTasks.splice(newIndex, 0, tasks[oldIndex])
    })

    return suggestedTasks
  }
)

export const selectIsSuggestionsModalOpen = createSelector(
  selectSuggestedTasks,
  state => state.suggestions.showSuggestions,
  (suggestedTasks, showSuggestions) => suggestedTasks.length > 0 && showSuggestions
)
