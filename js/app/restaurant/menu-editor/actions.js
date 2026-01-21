import { createAction } from '@reduxjs/toolkit'
import _ from 'lodash'

const httpClient = new window._auth.httpClient();

export const fetchProductsSuccess = createAction('MENU_EDITOR/FETCH_PRODUCTS_SUCCESS');
export const updateSectionProducts = createAction('MENU_EDITOR/UPDATE_SECTION_PRODUCTS');

export function fetchProducts(restaurant) {
  return async function(dispatch, getState) {
    try {
      const { response, error } = await httpClient.get(restaurant['@id'] + '/products');
      dispatch(fetchProductsSuccess(response['hydra:member']));
    } catch (e) {
      console.error(e);
    }
  }
}

export function removeProductFromSection(productId) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const section = _.find(menu.hasMenuSection, (s) => _.findIndex(s.hasMenuItem, (i) => i['@id'] === productId) !== -1);

    if (section) {

      console.log('removeProductFromSection', productId, section['@id'])

      const itemsWithoutProduct = section.hasMenuItem.filter((i) => i['@id'] !== productId)

      dispatch(setSectionProducts(section['@id'], itemsWithoutProduct));
    }
  }
}

export function setSectionProducts(sectionId, products) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const section = _.find(menu.hasMenuSection, (s) => s['@id'] === sectionId);

    if (section) {
      dispatch(updateSectionProducts({
        section,
        products
      }));
    }

    try {

      const { response, error } = await httpClient.put(sectionId, {
        products: products.map((p) => p['@id'])
      });

      if (error) {
        console.error(error);
      }

    } catch (e) {
      console.error(e);
    }
  }
}

export function moveProductToSection(product, index, sectionId) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    console.log('moveProductToSection', product, index, sectionId)

    const section = _.find(menu.hasMenuSection, (s) => s['@id'] === sectionId);

    dispatch(removeProductFromSection(product['@id']));

    const newProducts = Array.from(section.hasMenuItem);
    newProducts.splice(index, 0, product);

    dispatch(setSectionProducts(sectionId, newProducts))
  }
}
