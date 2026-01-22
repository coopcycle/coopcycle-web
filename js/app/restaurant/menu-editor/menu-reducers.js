const initialState = {
  name: '',
  hasMenuSection: [],
  isModalOpen: false,
}

import {
  updateSectionProducts,
  setMenuSections,
  openModal,
  closeModal,
} from './actions'

export default (state = initialState, action) => {
  switch (action.type) {
    case updateSectionProducts.type:

        const { section, products } = action.payload;

        const sectionIndex = _.findIndex(state.hasMenuSection, (s) => s['@id'] === section['@id']);

        const newSection = {
            ...section,
            hasMenuItem: products,
        }

        const newSections = state.hasMenuSection.slice();

        newSections.splice(sectionIndex, 1, newSection);

        return {
            ...state,
            hasMenuSection: newSections
        }

    case setMenuSections.type:

      return {
        ...state,
        hasMenuSection: Array.from(action.payload),
      }

    case openModal.type:
      return {
        ...state,
        isModalOpen: true,
      }

    case closeModal.type:
      return {
        ...state,
        isModalOpen: false,
      }
  }

  return state
}
