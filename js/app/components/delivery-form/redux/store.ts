import { configureStore, combineReducers } from '@reduxjs/toolkit';
import { accountSlice } from '../../../entities/account/reduxSlice';
import { apiSlice } from '../../../api/slice';
import { recurrenceSlice } from './recurrenceSlice';
import { suggestionsSlice } from './suggestionsSlice';
import { formSlice } from './formSlice';

// As we are using preloaded state we need to use combineReducers manually to infer RootState type
const rootReducer = combineReducers({
  [accountSlice.name]: accountSlice.reducer,
  [apiSlice.reducerPath]: apiSlice.reducer,
  [formSlice.name]: formSlice.reducer,
  [recurrenceSlice.name]: recurrenceSlice.reducer,
  [suggestionsSlice.name]: suggestionsSlice.reducer,
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
