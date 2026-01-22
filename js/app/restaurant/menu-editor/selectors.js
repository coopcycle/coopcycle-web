import {
  createSelector,
  // createEntityAdapter,
} from '@reduxjs/toolkit'
import _ from 'lodash';

export const selectMenu = (state) => state.menu

export const selectMenuName = (state) => state.menu.name

export const selectMenuSections = createSelector(
  selectMenu,
  (menu) => menu.hasMenuSection
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
  (menuProducts, allProducts) => _.filter(allProducts, (p) => !menuProducts.includes(p['@id']))
)

export const selectIsModalOpen = (state) => state.menu.isModalOpen;
