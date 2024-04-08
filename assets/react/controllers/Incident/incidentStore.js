import { createSlice, configureStore } from "@reduxjs/toolkit";

const initialState = {
  loaded: false,
  incident: null,
  order: null,
  images: [],
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
  },
});

const store = configureStore({
  reducer: incidentSlice.reducer,
});

export const { setLoaded, setIncident, setOrder, setImages } =
  incidentSlice.actions;

export default store;
