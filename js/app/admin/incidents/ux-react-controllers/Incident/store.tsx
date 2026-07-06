import React from 'react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';
import { apiSlice } from '../../../../api/slice';
import { accountSlice } from '../../../../entities/account/reduxSlice';
import { AppRootWithDefaults } from '../../../../utils/react';

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  };
};

const store = configureStore({
  reducer: {
    [accountSlice.name]: accountSlice.reducer,
    [apiSlice.reducerPath]: apiSlice.reducer,
  },
  preloadedState: buildInitialState(),
  middleware: getDefaultMiddleware =>
    getDefaultMiddleware().concat(apiSlice.middleware),
});

export const connectWithRedux =
  Component =>
  ({ ...props }) => (
    <AppRootWithDefaults>
      <Provider store={store}>
        <Component {...props} />
      </Provider>
    </AppRootWithDefaults>
  );
