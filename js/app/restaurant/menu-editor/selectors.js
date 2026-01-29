import {
  createSelector,
} from '@reduxjs/toolkit'
import _ from 'lodash';

export const selectMenu = (state) => state.menu

export const selectMenuName = (state) => state.menu.name

export const selectMenuSections = createSelector(
  selectMenu,
  (menu) => menu.hasMenuSection || []
)

const selectMenuProducts = createSelector(
  selectMenuSections,
  (sections) => {
    const products = [];
    sections.forEach((section) => {
      const productIds = section.hasMenuItem.map((item) => item['@id']);
      products.push(...productIds)
    });

    return products;
  }
)

const selectAllProducts = (state) => state.products.products

export const selectProducts = createSelector(
  selectMenuProducts,
  selectAllProducts,
  (menuProducts, allProducts) => _.sortBy(_.filter(allProducts, (p) => !menuProducts.includes(p['@id'])), ['name'])
)

export const selectIsModalOpen = (state) => state.ui.isModalOpen;

export const selectSectionInModal = (state) => state.ui.sectionInModal;

export const selectIsLoading = (state) => state.ui.isLoading;
export const selectIsLoadingProducts = (state) => state.ui.isLoadingProducts;
