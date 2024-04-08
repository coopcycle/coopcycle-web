import store, {
  setLoaded,
  setIncident,
  setOrder,
  setImages,
} from "./incidentStore";

export default function ({ incident, order, images }) {
  incident = JSON.parse(incident);
  order = JSON.parse(order);
  images = JSON.parse(images);

  store.dispatch(setIncident(incident));
  store.dispatch(setOrder(order));
  store.dispatch(setImages(images));
  store.dispatch(setLoaded(true));
  return;
}
