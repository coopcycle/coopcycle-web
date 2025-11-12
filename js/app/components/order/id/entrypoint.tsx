import React from 'react';
import { createRoot } from 'react-dom/client';
import ClipboardJS from 'clipboard';
import { RootWithDefaults } from '../../../utils/react';
import i18n from '../../../i18n';
import { Content } from './Content';
import { accountSlice } from '../../../entities/account/reduxSlice';
import { createStoreFromPreloadedState } from './redux/store';
import { Provider } from 'react-redux';

new ClipboardJS('#copy');

$('[data-change-state] button[type="submit"]').on('click', function (e) {
  const message = $(e.target).data('message');

  if (!window.confirm(message || i18n.t('ARE_YOU_SURE'))) {
    e.preventDefault();
  }
});

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  };
};

const store = createStoreFromPreloadedState(buildInitialState());

const el = document.querySelector('#react-root');

if (el) {
  const order = JSON.parse(el.dataset.order);
  // delivery can be empty for foodtech takeaway orders
  const delivery = el.dataset.delivery ? JSON.parse(el.dataset.delivery) : null;

  const root = createRoot(el);
  root.render(
    <RootWithDefaults>
      <Provider store={store}>
        <Content order={order} delivery={delivery} />
      </Provider>
    </RootWithDefaults>,
  );
}
