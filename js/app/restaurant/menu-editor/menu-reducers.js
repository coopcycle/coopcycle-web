const initialState = {
  name: '',
  hasMenuSection: [],
}

import {
  updateSectionProducts
} from './actions'

export default (state = initialState, action) => {
  switch (action.type) {
    case updateSectionProducts.type:

        const { section, sectionIndex, products } = action.payload

        // const newSection = {
        //     ...section,
        //     hasMenuItem: products,
        // }

        const newSections = state.hasMenuSection.slice();

        newSections.splice(sectionIndex, 1, {
          ...state.hasMenuSection[sectionIndex],
          hasMenuItem: products
        });

        return {
            ...state,
            hasMenuSection: newSections
        }
  }

  return state
}
