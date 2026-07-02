import React from 'react';
import { createRoot } from 'react-dom/client';
import Page from './page';
import { AppRootWithDefaults } from '../../utils/react';

import '../../bootstrap-reset.scss';
import '../../admin/shift-planning/styles.scss';

// Mount the component to the DOM when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('react-root');
  if (container) {
    const root = createRoot(container);
    root.render(
      <AppRootWithDefaults>
        <Page />
      </AppRootWithDefaults>,
    );
  }
});
