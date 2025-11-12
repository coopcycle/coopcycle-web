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
import DeliveryForm from '../../components/delivery-form/DeliveryForm';
import Modal from 'react-modal';

import '../../bootstrap-reset.scss';

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
    <FlagsContext.Provider
      value={{ isDispatcher, isDebugPricing, isPriceBreakdownEnabled }}>
      <DeliveryForm
        storeNodeId={storeNodeId}
        deliveryId={deliveryId}
        deliveryNodeId={deliveryNodeId}
        delivery={delivery ? JSON.parse(delivery) : null}
        order={order ? JSON.parse(order) : null}
        preLoadedFormData={formData ? JSON.parse(formData) : null}
      />
    </FlagsContext.Provider>
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
        />
      </Provider>
    </AppRootWithDefaults>,
  );
});
