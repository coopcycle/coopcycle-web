import { combineReducers, configureStore } from '@reduxjs/toolkit';
import { accountSlice } from '../../../../entities/account/reduxSlice';
import { apiSlice } from '../../../../api/slice';

// As we are using preloaded state we need to use combineReducers manually to infer RootState type
const rootReducer = combineReducers({
  [accountSlice.name]: accountSlice.reducer,
  [apiSlice.reducerPath]: apiSlice.reducer,
});

export type RootState = ReturnType<typeof rootReducer>;

export function createStoreFromPreloadedState(
  preloadedState: Partial<RootState>,
) {
  return configureStore({
    reducer: rootReducer,
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware().concat(apiSlice.middleware),
  });
}
