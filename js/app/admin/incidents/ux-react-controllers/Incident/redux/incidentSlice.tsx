import { createSlice } from '@reduxjs/toolkit';
import { Incident, Order } from '../../../../../api/types';
import { RootState } from './incidentStore';

const initialState = {
  loaded: false,
  incident: null as Incident | null,
  order: null as Order | null,
  images: [],
  transporterEnabled: false,
};

export const incidentSlice = createSlice({
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

export const {
  setLoaded,
  setIncident,
  setOrder,
  setImages,
  setEvents,
  setTransporterEnabled,
} = incidentSlice.actions;

export const selectLoaded = (state: RootState) => state.incident.loaded;
export const selectIncident = (state: RootState) => state.incident.incident;
export const selectOrder = (state: RootState) => state.incident.order;
export const selectImages = (state: RootState) => state.incident.images;
export const selectTransporterEnabled = (state: RootState) =>
  state.incident.transporterEnabled;
