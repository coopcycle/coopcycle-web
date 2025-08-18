import { createSlice, PayloadAction } from '@reduxjs/toolkit';
import { Mode, ModeType } from '../mode';
import { RootState } from './store';

const initialState = {
  mode: Mode.DELIVERY_CREATE as ModeType,
};

const slice = createSlice({
  name: 'form',
  initialState,
  reducers: {
    setMode: (state, action: PayloadAction<ModeType>) => {
      state.mode = action.payload;
    },
  },
});

// Action creators are generated for each case reducer function
export const { setMode } = slice.actions;

export const formSlice = slice;

export const selectMode = (state: RootState) => state.form.mode;
