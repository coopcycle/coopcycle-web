import {
  fetchProductsSuccess,
  updateSectionProducts,
} from './actions'

const initialState = {
  products: [],
}

export default (state = initialState, action) => {
  switch (action.type) {
    case fetchProductsSuccess.type:

      return {
        ...state,
        products: action.payload,
      };

    /*
    case updateSectionProducts.type:

      const { products } = action.payload

      return {
        ...state,
        products,
      };
    */
  }

  return state
}
