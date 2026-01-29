const initialState = {
  isModalOpen: false,
  sectionInModal: {
    name: '',
    description: ''
  },
  isLoading: false,
  isLoadingProducts: false,
}

import {
  openModal,
  closeModal,
  createSectionFlow,
  editSectionFlow,
  setIsLoading,
  setIsLoadingProducts,
  fetchProductsSuccess,
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

    case setIsLoading.type:
      return {
        ...state,
        isLoading: action.payload,
      }

    case setIsLoadingProducts.type:
      return {
        ...state,
        isLoadingProducts: action.payload,
      }

    case fetchProductsSuccess.type:
      return {
        ...state,
        isLoadingProducts: false,
      }
  }

  return state
}
