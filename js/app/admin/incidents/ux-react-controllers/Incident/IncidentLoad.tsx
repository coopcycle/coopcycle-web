import store from '../../[id]/redux/incidentStore';
import {
  setImages,
  setIncident,
  setLoaded,
  setOrder,
  setServiceTaxRate,
  setStoreUri,
  setTransporterEnabled,
} from '../../[id]/redux/incidentSlice';

export default function ({
  incident,
  order,
  images,
  storeUri,
  transporterEnabled,
  serviceTaxRate,
}) {
  incident = JSON.parse(incident);
  order = JSON.parse(order);
  images = JSON.parse(images);

  store.dispatch(setIncident(incident));
  store.dispatch(setOrder(order));
  store.dispatch(setStoreUri(storeUri));
  store.dispatch(setImages(images));
  store.dispatch(setTransporterEnabled(transporterEnabled));
  store.dispatch(setServiceTaxRate(serviceTaxRate));
  store.dispatch(setLoaded(true));
  return;
}
