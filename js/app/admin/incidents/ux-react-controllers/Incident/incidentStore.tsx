import React from 'react';
import {
  Provider,
  TypedUseSelectorHook,
  useDispatch,
  useSelector,
} from 'react-redux';
import { configureStore, createSlice } from '@reduxjs/toolkit';
import { Incident, Order } from '../../../../api/types';

const initialState = {
  loaded: false,
  incident: null as Incident | null,
  order: null as Order | null,
  images: [],
  transporterEnabled: false,
};

const incidentSlice = createSlice({
  name: 'incident',
  initialState,
  reducers: {
    setLoaded(state, action) {
      state.loaded = action.payload;
    },
    setIncident(state, action) {
      state.incident = action.payload;
    },
    setOrder(state, action) {
      state.order = action.payload;
    },
    setImages(state, action) {
      state.images = action.payload;
    },
    setEvents(state, action) {
      state.incident.events = action.payload;
    },
    setTransporterEnabled(state, action) {
      state.transporterEnabled = action.payload;
    },
  },
});

const store = configureStore({
  reducer: incidentSlice.reducer,
});

export const {
  setLoaded,
  setIncident,
  setOrder,
  setImages,
  setEvents,
  setTransporterEnabled,
} = incidentSlice.actions;

export default store;

// Infer the `RootState` and `AppDispatch` types from the store itself
export type RootState = ReturnType<typeof store.getState>;
// Inferred type: {posts: PostsState, comments: CommentsState, users: UsersState}
export type AppDispatch = typeof store.dispatch;

// Use throughout your app instead of plain `useDispatch` and `useSelector`
export const useAppDispatch: () => AppDispatch = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
// TODO; replace with after migrating to react-redux v9.0
// export const useAppDispatch = useDispatch.withTypes<AppDispatch>()
// export const useAppSelector = useSelector.withTypes<RootState>()

export const connectWithRedux =
  Component =>
  ({ ...props }) => (
    <Provider store={store}>
      <Component {...props} />
    </Provider>
  );

export const selectLoaded = (state: RootState) => state.loaded;
export const selectIncident = (state: RootState) => state.incident;
export const selectOrder = (state: RootState) => state.order;
export const selectImages = (state: RootState) => state.images;
export const selectTransporterEnabled = (state: RootState) =>
  state.transporterEnabled;
