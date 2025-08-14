import { createSlice } from '@reduxjs/toolkit';
import { Mode } from '../mode';

const initialState = {
  mode: Mode.DELIVERY_CREATE,
};

const slice = createSlice({
  name: 'form',
  initialState,
  reducers: {
    setMode: (state, action) => {
      state.mode = action.payload;
    },
  },
});

// Action creators are generated for each case reducer function
export const { setMode } = slice.actions;

export const formSlice = slice;

export const selectMode = state => state.form.mode;
