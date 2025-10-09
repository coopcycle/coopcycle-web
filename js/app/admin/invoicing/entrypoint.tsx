import React from 'react';
import { createRoot } from 'react-dom/client';
import Modal from 'react-modal';
import Page from './page';
import { RootWithDefaults } from '../../utils/react';

import '../../bootstrap-reset.scss'

// Mount the component to the DOM when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('react-root');
  if (container) {
    Modal.setAppElement('#react-root');

    const root = createRoot(container);
    root.render(
      <RootWithDefaults>
        <Page />
      </RootWithDefaults>,
    );
  }
});
