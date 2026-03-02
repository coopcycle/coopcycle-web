import React, { useLayoutEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { AppRootWithDefaults } from '../../utils/react';
import { accountSlice } from '../../entities/account/reduxSlice';
import { createStoreFromPreloadedState } from '../../components/delivery-form/redux/store';
import { Uri } from '../../api/types';
import { Provider, useDispatch } from 'react-redux';
import { setMode } from '../../components/delivery-form/redux/formSlice';
import { Mode } from '../../components/delivery-form/mode';
import FlagsContext from '../../components/delivery-form/FlagsContext';
import UploadContext from '../../components/delivery-form/UploadContext';
import DeliveryForm from '../../components/delivery-form/DeliveryForm';
import Modal from 'react-modal';

import '../../bootstrap-reset.scss';
import { UserContext } from '../../UserContext';

import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  };
};

const store = createStoreFromPreloadedState(buildInitialState());

type Props = {
  storeNodeId: Uri;
  deliveryId?: number;
  deliveryNodeId?: Uri;
  delivery?: string;
  order?: string;
  formData?: string;
  isDispatcher: boolean;
  isDebugPricing: boolean;
  isPriceBreakdownEnabled: boolean;
<<<<<<< HEAD
  documentUploadEndpoint: string;
=======
  isReverseDeliveryEnabled: boolean;
>>>>>>> master
};

const Form = ({
  storeNodeId,
  deliveryId,
  deliveryNodeId,
  delivery,
  order,
  formData,
  isDispatcher,
  isDebugPricing,
  isPriceBreakdownEnabled,
<<<<<<< HEAD
  documentUploadEndpoint,
=======
  isReverseDeliveryEnabled,
>>>>>>> master
}: Props) => {
  const dispatch = useDispatch();

  useLayoutEffect(() => {
    dispatch(
      setMode(
        Boolean(deliveryNodeId) ? Mode.DELIVERY_UPDATE : Mode.DELIVERY_CREATE,
      ),
    );
  }, [dispatch, deliveryNodeId]);

  return (
    <UserContext.Provider value={{ isDispatcher }}>
      <FlagsContext.Provider
        value={{ isDebugPricing, isPriceBreakdownEnabled, isReverseDeliveryEnabled }}>
        <UploadContext.Provider value={{ endpoint: documentUploadEndpoint }}>
          <DeliveryForm
            storeNodeId={storeNodeId}
            deliveryId={deliveryId}
            deliveryNodeId={deliveryNodeId}
            delivery={delivery ? JSON.parse(delivery) : null}
            order={order ? JSON.parse(order) : null}
            preLoadedFormData={formData ? JSON.parse(formData) : null}
            />
        </UploadContext.Provider>
      </FlagsContext.Provider>
    </UserContext.Provider>
  );
};

// Mount the component to the DOM when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('delivery-form');
  if (!container) {
    return;
  }

  Modal.setAppElement('.content');

  const storeNodeId = container.dataset.storeNodeId;
  const deliveryId = container.dataset.deliveryId
    ? parseInt(container.dataset.deliveryId, 10)
    : undefined;
  const deliveryNodeId = container.dataset.deliveryNodeId || undefined;
  const delivery = container.dataset.delivery || undefined;
  const order = container.dataset.order || undefined;
  const formData = container.dataset.formData || undefined;
  const isDispatcher = container.dataset.isDispatcher === 'true';
  const isDebugPricing = container.dataset.isDebugPricing === 'true';
  const isPriceBreakdownEnabled =
    container.dataset.isPriceBreakdownEnabled === 'true';
<<<<<<< HEAD
  const documentUploadEndpoint = container.dataset.documentUploadEndpoint;
=======
  const isReverseDeliveryEnabled =
    container.dataset.isReverseDeliveryEnabled === 'true';
>>>>>>> master

  const root = createRoot(container);
  root.render(
    <AppRootWithDefaults>
      <Provider store={store}>
        <Form
          storeNodeId={storeNodeId}
          deliveryId={deliveryId}
          deliveryNodeId={deliveryNodeId}
          delivery={delivery}
          order={order}
          formData={formData}
          isDispatcher={isDispatcher}
          isDebugPricing={isDebugPricing}
          isPriceBreakdownEnabled={isPriceBreakdownEnabled}
<<<<<<< HEAD
          documentUploadEndpoint={documentUploadEndpoint}
=======
          isReverseDeliveryEnabled={isReverseDeliveryEnabled}
>>>>>>> master
        />
      </Provider>
    </AppRootWithDefaults>,
  );
});
