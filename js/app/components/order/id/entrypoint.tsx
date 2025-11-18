import React from 'react';
import { createRoot } from 'react-dom/client';
import ClipboardJS from 'clipboard';
import { RootWithDefaults } from '../../../utils/react';
import Map from '../../../components/DeliveryMap';
import Itinerary from '../../../components/DeliveryItinerary';
import i18n from '../../../i18n';
import { UserContext } from '../../../UserContext';

new ClipboardJS('#copy');

$('[data-change-state] button[type="submit"]').on('click', function (e) {
  const message = $(e.target).data('message');

  if (!window.confirm(message || i18n.t('ARE_YOU_SURE'))) {
    e.preventDefault();
  }
});

const el = document.querySelector('#delivery-info');

if (el) {
  const delivery = JSON.parse(el.dataset.delivery);
  const isDispatcher = el.dataset.isDispatcher === 'true';

  const root = createRoot(el);
  root.render(
    <RootWithDefaults>
      <UserContext.Provider value={{ isDispatcher }}>
        <div>
          <Map
            defaultAddress={delivery.tasks[0].address}
            tasks={delivery.tasks}
          />
          <div className="py-3" />
          <Itinerary
            tasks={delivery.tasks}
            withTaskLinks={isDispatcher}
            withTimeRange
            withDescription
            withPackages
          />
        </div>
      </UserContext.Provider>
    </RootWithDefaults>,
  );
}
