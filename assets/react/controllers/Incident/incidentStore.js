import React from "react";
import { Provider } from "react-redux";
import { createSlice, configureStore } from "@reduxjs/toolkit";

const initialState = {
  loaded: false,
  incident: null,
  order: null,
  images: [],
  transporterEnabled: false,
};

const incidentSlice = createSlice({
  name: "incident",
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

export const connectWithRedux =
  (Component) =>
  ({ ...props }) => (
    <Provider store={store}>
      <Component {...props} />
    </Provider>
  );
