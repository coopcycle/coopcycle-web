import store from '../../[id]/redux/incidentStore';
import {
  setImages,
  setIncident,
  setLoaded,
  setOrder,
  setStoreUri,
  setTransporterEnabled,
} from '../../[id]/redux/incidentSlice';

export default function ({
  incident,
  order,
  images,
  storeUri,
  transporterEnabled,
}) {
  incident = JSON.parse(incident);
  order = JSON.parse(order);
  images = JSON.parse(images);

  store.dispatch(setIncident(incident));
  store.dispatch(setOrder(order));
  store.dispatch(setStoreUri(storeUri));
  store.dispatch(setImages(images));
  store.dispatch(setTransporterEnabled(transporterEnabled));
  store.dispatch(setLoaded(true));
  return;
}
