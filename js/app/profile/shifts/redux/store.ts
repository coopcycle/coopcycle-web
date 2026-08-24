import { configureStore } from '@reduxjs/toolkit';
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
