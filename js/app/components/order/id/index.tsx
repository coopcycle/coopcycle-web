import React from 'react';
import { createRoot } from 'react-dom/client';
import ClipboardJS from 'clipboard';
import { RootWithDefaults } from '../../../utils/react';
import Map from '../../../components/DeliveryMap';
import Itinerary from '../../../components/DeliveryItinerary';
import i18n from '../../../i18n';

new ClipboardJS('#copy');

$('[data-change-state] button[type="submit"]').on('click', function (e) {
  const message = $(e.target).data('message');

  if (!window.confirm(message || i18n.t('ARE_YOU_SURE'))) {
    e.preventDefault();
  }
});

const el = document.querySelector('#react-root');

if (el) {
  const order = JSON.parse(el.dataset.order);
  // delivery can be empty for foodtech takeaway orders
  const delivery = el.dataset.delivery ? JSON.parse(el.dataset.delivery) : null;

  const root = createRoot(el);
  root.render(
    <RootWithDefaults>
      {delivery ? (
        <div>
          <Map
            defaultAddress={delivery.tasks[0].address}
            tasks={delivery.tasks}
          />
          <div className="py-3" />
          <Itinerary
            tasks={delivery.tasks}
            withTimeRange
            withDescription
            withPackages
          />
        </div>
      ) : null}
    </RootWithDefaults>,
  );
}
