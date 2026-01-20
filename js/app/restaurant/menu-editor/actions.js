import { createAction } from '@reduxjs/toolkit'
import _ from 'lodash'

const httpClient = new window._auth.httpClient();

export const fetchProductsSuccess = createAction('MENU_EDITOR/FETCH_PRODUCTS_SUCCESS');
export const updateSectionProducts = createAction('MENU_EDITOR/UPDATE_SECTION_PRODUCTS');

export function fetchProducts(restaurant) {
  return async function(dispatch, getState) {
    try {
      const { response, error } = await httpClient.get(restaurant['@id'] + '/products');
      console.log(response)
      // setIsLoading(false)
      dispatch(fetchProductsSuccess(response['hydra:member']));
    } catch (e) {
      console.error(e);
    }
  }
}

// const findProductIndex = (items, productId) => _.findIndex(items, (i) => i['@id'] === productId)

export function removeProductFromSection(productId) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const sectionIndex = _.findIndex(menu.hasMenuSection, (s) => _.findIndex(s.hasMenuItem, (i) => i['@id'] === productId) !== -1);

    if (sectionIndex !== -1) {
      const section = menu.hasMenuSection[sectionIndex];

      console.log('removeProductFromSection', productId, section['@id'])

      // const productIndex = findProductIndex(section.hasMenuItem, productId);

      // const newSection = {
      //   ...section,
      //   hasMenuItem: section.hasMenuItem.filter((i) => i['@id'] !== productId)
      // }

      dispatch(updateSectionProducts({
        section,
        sectionIndex,
        products: section.hasMenuItem.filter((i) => i['@id'] !== productId)
      }));


      // console.log('newSection', newSection)
    }



    // try {
    //   const { response, error } = await httpClient.get(restaurant['@id'] + '/products');
    //   console.log(response)
    //   // setIsLoading(false)
    //   dispatch(fetchProductsSuccess(response['hydra:member']));
    // } catch (e) {
    //   console.error(e);
    // }
  }
}

export function addProductToSection(productId, sectionId) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const sectionIndex = _.findIndex(menu.hasMenuSection, (s) => s['@id'] === sectionId);

    if (sectionIndex !== -1) {
      const section = menu.hasMenuSection[sectionIndex];

      console.log('addProductToSection', productId, section['@id'])

      // const productIndex = findProductIndex(section.hasMenuItem, productId);

      // const newSection = {
      //   ...section,
      //   hasMenuItem: section.hasMenuItem.filter((i) => i['@id'] !== productId)
      // }

      // dispatch(updateSectionProducts({
      //   section,
      //   sectionIndex,
      //   products: section.hasMenuItem.filter((i) => i['@id'] !== productId)
      // }));


      // console.log('newSection', newSection)
    }



    // try {
    //   const { response, error } = await httpClient.get(restaurant['@id'] + '/products');
    //   console.log(response)
    //   // setIsLoading(false)
    //   dispatch(fetchProductsSuccess(response['hydra:member']));
    // } catch (e) {
    //   console.error(e);
    // }
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

    // try {
    //   const { response, error } = await httpClient.get(restaurant['@id'] + '/products');
    //   console.log(response)
    //   // setIsLoading(false)
    //   dispatch(fetchProductsSuccess(response['hydra:member']));
    // } catch (e) {
    //   console.error(e);
    // }
  }
}
