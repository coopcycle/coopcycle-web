import { createAction } from '@reduxjs/toolkit'
import _ from 'lodash'

const httpClient = new window._auth.httpClient();

export const fetchProductsSuccess = createAction('MENU_EDITOR/FETCH_PRODUCTS_SUCCESS');
export const updateSectionProducts = createAction('MENU_EDITOR/UPDATE_SECTION_PRODUCTS');
export const setMenuSections = createAction('MENU_EDITOR/SET_MENU_SECTIONS');
export const openModal = createAction('MENU_EDITOR/OPEN_MODAL');
export const closeModal = createAction('MENU_EDITOR/CLOSE_MODAL');

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

    const section = _.find(menu.hasMenuSection, (s) => s['@id'] === sectionId);

    dispatch(removeProductFromSection(product['@id']));

    const newProducts = Array.from(section.hasMenuItem);
    newProducts.splice(index, 0, product);

    dispatch(setSectionProducts(sectionId, newProducts))
  }
}

export function updateSectionsOrder(sections) {
  return async function(dispatch, getState) {
    dispatch(setMenuSections(sections));

    try {

      const { menu } = getState();

      const { response, error } = await httpClient.put(menu['@id'], {
        sections: sections.map((s) => s['@id'])
      });

      if (error) {
        console.error(error);
      }

    } catch (e) {
      console.error(e);
    }
  }
}

export function addSection(name) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const newSections = Array.from(menu.hasMenuSection);
    newSections.push({
      name,
      hasMenuItem: [],
    })

    try {

      const { response, error } = await httpClient.post(menu['@id'] + '/sections', {
        name
      });

      if (error) {
        console.error(error);
      } else {
        dispatch(setMenuSections(response.hasMenuSection));
      }

      dispatch(closeModal());

    } catch (e) {
      console.error(e);
    }
  }
}

export function deleteSection(section) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const sectionIndex = menu.hasMenuSection.findIndex((s) => s['@id'] === section['@id']);

    const newSections = Array.from(menu.hasMenuSection);
    newSections.splice(sectionIndex, 1);

    dispatch(setMenuSections(newSections));

    try {

      const { response, error } = await httpClient.delete(section['@id'], {
        name
      });

      if (error) {
        console.error(error);
      }

    } catch (e) {
      console.error(e);
    }
  }
}

export function setSectionName(sectionId, name) {
  return async function(dispatch, getState) {

    const { menu } = getState();

    const sectionIndex = menu.hasMenuSection.findIndex((s) => s['@id'] === sectionId);
    const section = _.find(menu.hasMenuSection, (s) => s['@id'] === sectionId);

    const newSection = {
      ...section,
      name
    }

    const newSections = Array.from(menu.hasMenuSection);
    newSections.splice(sectionIndex, 1, newSection);

    dispatch(setMenuSections(newSections));

    try {

      const { response, error } = await httpClient.put(section['@id'], newSection);

      if (error) {
        console.error(error);
      }

    } catch (e) {
      console.error(e);
    }
  }
}
