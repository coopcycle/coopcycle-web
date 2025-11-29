import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  rule: null,
  isCancelled: false,
  modalIsOpen: false,
};

const slice = createSlice({
  name: 'recurrence',
  initialState,
  reducers: {
    openRecurrenceModal: state => {
      state.modalIsOpen = true;
    },
    closeRecurrenceModal: state => {
      state.modalIsOpen = false;
    },
  },
});

// Action creators are generated for each case reducer function
export const { openRecurrenceModal, closeRecurrenceModal } = slice.actions;

export const recurrenceSlice = slice;

export const selectIsRecurrenceModalOpen = state =>
  state.recurrence.modalIsOpen;
