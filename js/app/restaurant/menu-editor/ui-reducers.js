const initialState = {
  isModalOpen: false,
  sectionInModal: {
    name: '',
    description: ''
  }
}

import {
  openModal,
  closeModal,
  createSectionFlow,
  editSectionFlow,
} from './actions'

export default (state = initialState, action) => {
  switch (action.type) {

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

    case createSectionFlow.type:
      return {
        ...state,
        isModalOpen: true,
        sectionInModal: initialState.sectionInModal,
      }

    case editSectionFlow.type:
      return {
        ...state,
        isModalOpen: true,
        sectionInModal: action.payload,
      }
  }

  return state
}
