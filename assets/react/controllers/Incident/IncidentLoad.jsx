import store, {
  setLoaded,
  setIncident,
  setOrder,
  setImages,
  setTransporterEnabled,
} from "./incidentStore";

export default function ({ incident, order, images, transporterEnabled }) {
  incident = JSON.parse(incident);
  order = JSON.parse(order);
  images = JSON.parse(images);

  store.dispatch(setIncident(incident));
  store.dispatch(setOrder(order));
  store.dispatch(setImages(images));
  store.dispatch(setTransporterEnabled(transporterEnabled));
  store.dispatch(setLoaded(true));
  return;
}
