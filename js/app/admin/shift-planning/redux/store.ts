import { configureStore } from '@reduxjs/toolkit';
import { TypedUseSelectorHook, useDispatch, useSelector } from 'react-redux';
import { accountSlice } from '../../../entities/account/reduxSlice';
import { apiSlice } from '../../../api/slice';

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  };
};

export const store = configureStore({
  reducer: {
    [accountSlice.name]: accountSlice.reducer,
    [apiSlice.reducerPath]: apiSlice.reducer,
  },
  preloadedState: buildInitialState(),
  middleware: getDefaultMiddleware =>
    getDefaultMiddleware().concat(apiSlice.middleware),
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;

export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
