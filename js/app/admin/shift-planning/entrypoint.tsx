import React from 'react';
import { createRoot } from 'react-dom/client';
import Page from './page';
import { AppRootWithDefaults } from '../../utils/react';

import '../../bootstrap-reset.scss';
import './styles.scss';

// Mount the component to the DOM when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('react-root');
  if (container) {
    const shiftTypes = JSON.parse(container.dataset.shiftTypes || '[]');

    const root = createRoot(container);
    root.render(
      <AppRootWithDefaults>
        <Page shiftTypes={shiftTypes} />
      </AppRootWithDefaults>,
    );
  }
});
