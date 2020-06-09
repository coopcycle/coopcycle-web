import { combineReducers } from 'redux'

import {
  FETCH_REQUEST,
  FETCH_SUCCESS,
  FETCH_FAILURE,
  SET_STREET_ADDRESS,
  TOGGLE_MOBILE_CART,
  REPLACE_ERRORS,
  SET_LAST_ADD_ITEM_REQUEST,
  CLEAR_LAST_ADD_ITEM_REQUEST,
  SET_DATE_MODAL_OPEN,
  CLOSE_ADDRESS_MODAL,
  GEOCODING_FAILURE,
  ENABLE_TAKEAWAY,
  DISABLE_TAKEAWAY,
} from './actions'

const initialState = {
  cart: {
    restaurant: null,
    items: [],
    itemsTotal: 0,
    total: 0,
    adjustments: {},
    shippingAddress: null,
    shippedAt: null,
    shippingTimeRange: null,
    takeaway: false,
  },
  restaurant: null,
  isFetching: false,
  errors: [],
  addressFormElements: {},
  isNewAddressFormElement: null,
  datePickerTimeSlotInputName: 'timeSlot',
  isMobileCartVisible: false,
  addresses: [],
  lastAddItemRequest: null,
  times: {
    asap: null,
    fast: false,
    today: false,
    diff: '',
    range: null,
    ranges: [],
  },
  isDateModalOpen: false,
  isAddressModalOpen: false,
  country: 'fr',
}

const isFetching = (state = initialState.isFetching, action = {}) => {
  switch (action.type) {
  case FETCH_REQUEST:
    return true
  case FETCH_SUCCESS:
  case FETCH_FAILURE:
  case GEOCODING_FAILURE:
    return false
  default:
    return state
  }
}

const errors = (state = initialState.errors, action = {}) => {
  switch (action.type) {
  case FETCH_REQUEST:

    return []
  case FETCH_SUCCESS:
  case FETCH_FAILURE:
    const { errors } = action.payload

    return errors || []
  case REPLACE_ERRORS:
    const { propertyPath } = action.payload

    return {
      ...state,
      [propertyPath]: action.payload.errors
    }
  default:

    return state
  }
}

const cart = (state = initialState.cart, action = {}) => {
  switch (action.type) {
  case FETCH_SUCCESS:
  case FETCH_FAILURE:

    return action.payload.cart
  case SET_STREET_ADDRESS:

    return {
      ...state,
      shippingAddress: {
        ...state.shippingAddress,
        streetAddress: action.payload
      }
    }
  case GEOCODING_FAILURE:

    return {
      ...state,
      shippingAddress: {
        streetAddress: ''
      },
    }
  case ENABLE_TAKEAWAY:

    return {
      ...state,
      takeaway: true,
    }
  case DISABLE_TAKEAWAY:

    return {
      ...state,
      takeaway: false,
    }
  default:

    return state
  }
}

const lastAddItemRequest = (state = initialState.lastAddItemRequest, action = {}) => {
  switch (action.type) {
  case SET_LAST_ADD_ITEM_REQUEST:

    return action.payload
  case CLEAR_LAST_ADD_ITEM_REQUEST:

    return null
  default:

    return state
  }
}

const restaurant = (state = initialState.restaurant) => state
const addressFormElements = (state = initialState.addressFormElements) => state
const isNewAddressFormElement = (state = initialState.isNewAddressFormElement) => state
const datePickerTimeSlotInputName = (state = initialState.datePickerTimeSlotInputName) => state
const addresses = (state = initialState.addresses) => state
const country = (state = initialState.country) => state

const isMobileCartVisible = (state = initialState.isMobileCartVisible, action = {}) => {
  switch (action.type) {
  case TOGGLE_MOBILE_CART:

    return !state
  default:
    return state
  }
}

const isDateModalOpen = (state = initialState.isDateModalOpen, action = {}) => {
  switch (action.type) {
  case SET_DATE_MODAL_OPEN:

    return action.payload
  default:

    return state
  }
}

const times = (state = initialState.times, action = {}) => {
  switch (action.type) {
  case FETCH_SUCCESS:
  case FETCH_FAILURE:

    return action.payload.times
  default:

    return state
  }
}

const isAddressModalOpen = (state = initialState.isAddressModalOpen, action = {}) => {
  switch (action.type) {
  case FETCH_REQUEST:
  case CLOSE_ADDRESS_MODAL:

    return false

  case FETCH_SUCCESS:
  case FETCH_FAILURE:
    const { errors } = action.payload

    return Object.prototype.hasOwnProperty.call(errors, 'shippingAddress')
  case REPLACE_ERRORS:
    const { propertyPath } = action.payload

    return propertyPath === 'shippingAddress'
  default:

    return state
  }
}

export default combineReducers({
  isFetching,
  cart,
  restaurant,
  errors,
  addressFormElements,
  isNewAddressFormElement,
  datePickerTimeSlotInputName,
  isMobileCartVisible,
  addresses,
  lastAddItemRequest,
  times,
  isDateModalOpen,
  isAddressModalOpen,
  country,
})
