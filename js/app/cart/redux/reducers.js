import {combineReducers} from 'redux'

import {
  CLEAR_LAST_ADD_ITEM_REQUEST,
  CLOSE_ADDRESS_MODAL,
  CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL,
  CLOSE_PRODUCT_DETAILS_MODAL,
  CLOSE_PRODUCT_OPTIONS_MODAL,
  CLOSE_SET_PLAYER_EMAIL_MODAL,
  DISABLE_TAKEAWAY,
  ENABLE_TAKEAWAY,
  FETCH_FAILURE,
  FETCH_REQUEST,
  FETCH_SUCCESS,
  GEOCODING_FAILURE,
  INVITE_PEOPLE_REQUEST,
  INVITE_PEOPLE_REQUEST_FAILURE,
  INVITE_PEOPLE_REQUEST_SUCCESS,
  OPEN_ADDRESS_MODAL,
  OPEN_INVITE_PEOPLE_TO_ORDER_MODAL,
  OPEN_PRODUCT_DETAILS_MODAL,
  OPEN_PRODUCT_OPTIONS_MODAL,
  OPEN_SET_PLAYER_EMAIL_MODAL,
  PLAYER_UPDATE_EVENT,
  REPLACE_ERRORS,
  SET_DATE_MODAL_OPEN,
  SET_LAST_ADD_ITEM_REQUEST,
  SET_PLAYER_TOKEN,
  SET_STREET_ADDRESS,
  TOGGLE_MOBILE_CART,
  STOP_ASKING_ENABLE_REUSABLE_PACKAGING,
  ENABLE_REUSABLE_PACKAGING,
  DISABLE_REUSABLE_PACKAGING,
} from './actions'

const initialState = {
  cart: {
    items: [],
    itemsTotal: 0,
    total: 0,
    adjustments: {},
    shippingAddress: null,
    shippedAt: null,
    shippingTimeRange: null,
    takeaway: false,
    invitation: null,
    reusablePackagingEnabled: false,
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
  isProductOptionsModalOpen: false,
  productOptionsModalContext: {},
  isProductDetailsModalOpen: false,
  productDetailsModalContext: {},
  addressModalContext: {},
  isInvitePeopleToOrderModalOpen: false,
  invitePeopleToOrderContext: {},
  isPlayer: false,
  isSetPlayerEmailModalOpen: false,
  player: {
    token: null,
    player: null,
    centrifugo: {
      token: null,
      channel: null
    }
  },
  isGroupOrdersEnabled: false,
  shouldAskToEnableReusablePackaging: true,
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
  case INVITE_PEOPLE_REQUEST_SUCCESS:
    return {
      ...state,
      invitation: action.payload,
    }
  case PLAYER_UPDATE_EVENT:

    return action.payload
  case ENABLE_REUSABLE_PACKAGING:

    return {
      ...state,
      reusablePackagingEnabled: true,
    }
  case DISABLE_REUSABLE_PACKAGING:

    return {
      ...state,
      reusablePackagingEnabled: false,
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
    const { errors } = action.payload

    return Object.prototype.hasOwnProperty.call(errors, 'shippingAddress')
  case REPLACE_ERRORS:
    const { propertyPath } = action.payload

    return propertyPath === 'shippingAddress'
  case OPEN_ADDRESS_MODAL:

    return true
  default:

    return state
  }
}

const addressModalContext = (state = initialState.addressModalContext, action = {}) => {
  switch (action.type) {
  case CLOSE_ADDRESS_MODAL:

    return {}

  case OPEN_ADDRESS_MODAL:

    return action.payload
  }

  return state
}

const isProductOptionsModalOpen = (state = initialState.isProductOptionsModalOpen, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_OPTIONS_MODAL:

    return false

  case OPEN_PRODUCT_OPTIONS_MODAL:

    return true
  }

  return state
}

const productOptionsModalContext = (state = initialState.productOptionsModalContext, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_OPTIONS_MODAL:

    return {}

  case OPEN_PRODUCT_OPTIONS_MODAL:

    return action.payload
  }

  return state
}

const isProductDetailsModalOpen = (state = initialState.isProductDetailsModalOpen, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_DETAILS_MODAL:

    return false

  case OPEN_PRODUCT_DETAILS_MODAL:

    return true
  }

  return state
}

const productDetailsModalContext = (state = initialState.productDetailsModalContext, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_DETAILS_MODAL:

    return {}

  case OPEN_PRODUCT_DETAILS_MODAL:

    return action.payload
  }

  return state
}

const isInvitePeopleToOrderModalOpen = (state = initialState.isInvitePeopleToOrderModalOpen, action = {}) => {
  switch (action.type) {
  case CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL:
    return false
  case OPEN_INVITE_PEOPLE_TO_ORDER_MODAL:
    return true
  default:
    return state
  }
}

const invitePeopleToOrderContext = (state = initialState.invitePeopleToOrderContext, action = {}) => {
  switch (action.type) {
    case INVITE_PEOPLE_REQUEST:
      return {
        ...state,
        isRequesting: true,
        hasError: false,
      }

    case INVITE_PEOPLE_REQUEST_SUCCESS:
      return {
        ...state,
        isRequesting: false,
        hasError: false,
      }

    case INVITE_PEOPLE_REQUEST_FAILURE:
      return {
        ...state,
        isRequesting: false,
        hasError: true,
      }

    default:
      return state
  }
}

const isPlayer = (state = initialState.isPlayer) => {
  return state
}

const isSetPlayerEmailModalOpen = (state = initialState.isSetPlayerEmailModalOpen, action = {}) => {

  switch (action.type) {
  case CLOSE_SET_PLAYER_EMAIL_MODAL:
    return false
  case OPEN_SET_PLAYER_EMAIL_MODAL:
    return true
  default:
    return state
  }
}

const player = (state = initialState.player, action = {}) => {
  switch (action.type) {
    case SET_PLAYER_TOKEN:
      return action.payload
    default:
      return state
  }
}

const isGroupOrdersEnabled = (state = initialState.isGroupOrdersEnabled) => {
  return state
}

const shouldAskToEnableReusablePackaging = (state = initialState.shouldAskToEnableReusablePackaging, action = {}) => {
  switch (action.type) {
  case STOP_ASKING_ENABLE_REUSABLE_PACKAGING:
    return false
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
  isProductOptionsModalOpen,
  productOptionsModalContext,
  isProductDetailsModalOpen,
  productDetailsModalContext,
  addressModalContext,
  isInvitePeopleToOrderModalOpen,
  invitePeopleToOrderContext,
  isPlayer,
  isSetPlayerEmailModalOpen,
  player,
  isGroupOrdersEnabled,
  shouldAskToEnableReusablePackaging,
})
