import {
  CLEAR_LAST_ADD_ITEM_REQUEST,
  CLOSE_ADDRESS_MODAL,
  CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL,
  CLOSE_PRODUCT_OPTIONS_MODAL,
  CLOSE_SET_PLAYER_EMAIL_MODAL,
  DISABLE_TAKEAWAY,
  ENABLE_TAKEAWAY,
  FETCH_FAILURE,
  FETCH_REQUEST,
  FETCH_SUCCESS,
  GEOCODING_SUCCESS,
  GEOCODING_FAILURE,
  INVITE_PEOPLE_REQUEST,
  INVITE_PEOPLE_REQUEST_FAILURE,
  INVITE_PEOPLE_REQUEST_SUCCESS,
  OPEN_ADDRESS_MODAL,
  OPEN_INVITE_PEOPLE_TO_ORDER_MODAL,
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
  updateCartTiming,
} from './actions'
import { setShippingTimeRange } from '../../entities/order/reduxSlice'

export const initialState = {
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
  restaurantTiming: {
    // object shape:
    // delivery: {
    //   fast: false,
    //   today: false,
    //   diff: '',
    //   range: null,
    // },
    // collection: {
    //   fast: false,
    //   today: false,
    //   diff: '',
    //   range: null,
    // },
    firstChoiceKey: null,
  },
  cartTiming: {
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

export const isFetching = (state = initialState.isFetching, action = {}) => {
  switch (action.type) {
  case FETCH_REQUEST:
    return true
  case FETCH_SUCCESS:
  case FETCH_FAILURE:
  case GEOCODING_SUCCESS:
  case GEOCODING_FAILURE:
    return false
  default:
    return state
  }
}

export const errors = (state = initialState.errors, action = {}) => {
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

export const cart = (state = initialState.cart, action = {}) => {
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

  case setShippingTimeRange.type:

      return {
        ...state,
        shippingTimeRange: action.payload,
      }

  default:

    return state
  }
}

export const lastAddItemRequest = (state = initialState.lastAddItemRequest, action = {}) => {
  switch (action.type) {
  case SET_LAST_ADD_ITEM_REQUEST:

    return action.payload
  case CLEAR_LAST_ADD_ITEM_REQUEST:

    return null
  default:

    return state
  }
}

export const restaurant = (state = initialState.restaurant) => state
export const addressFormElements = (state = initialState.addressFormElements) => state
export const isNewAddressFormElement = (state = initialState.isNewAddressFormElement) => state
export const datePickerTimeSlotInputName = (state = initialState.datePickerTimeSlotInputName) => state
export const addresses = (state = initialState.addresses) => state
export const country = (state = initialState.country) => state

export const isMobileCartVisible = (state = initialState.isMobileCartVisible, action = {}) => {
  switch (action.type) {
  case TOGGLE_MOBILE_CART:

    return !state
  default:
    return state
  }
}

export const isDateModalOpen = (state = initialState.isDateModalOpen, action = {}) => {
  switch (action.type) {
  case SET_DATE_MODAL_OPEN:

    return action.payload
  default:

    return state
  }
}

export const restaurantTiming = (state = initialState.restaurantTiming, action = {}) => {
  switch (action.type) {
  case FETCH_SUCCESS:

    return action.payload.restaurantTiming
  default:

    return state
  }
}

export const cartTiming = (state = initialState.cartTiming, action = {}) => {
  switch (action.type) {
    case FETCH_SUCCESS:

      return action.payload.cartTiming
    case updateCartTiming.type:

      return action.payload
    default:

      return state
  }
}

export const isAddressModalOpen = (state = initialState.isAddressModalOpen, action = {}) => {
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

export const addressModalContext = (state = initialState.addressModalContext, action = {}) => {
  switch (action.type) {
  case CLOSE_ADDRESS_MODAL:

    return {}

  case OPEN_ADDRESS_MODAL:

    return action.payload
  }

  return state
}

export const isProductOptionsModalOpen = (state = initialState.isProductOptionsModalOpen, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_OPTIONS_MODAL:

    return false

  case OPEN_PRODUCT_OPTIONS_MODAL:

    return true
  }

  return state
}

export const productOptionsModalContext = (state = initialState.productOptionsModalContext, action = {}) => {
  switch (action.type) {
  case CLOSE_PRODUCT_OPTIONS_MODAL:

    return {}

  case OPEN_PRODUCT_OPTIONS_MODAL:

    return action.payload
  }

  return state
}

export const isInvitePeopleToOrderModalOpen = (state = initialState.isInvitePeopleToOrderModalOpen, action = {}) => {
  switch (action.type) {
  case CLOSE_INVITE_PEOPLE_TO_ORDER_MODAL:
    return false
  case OPEN_INVITE_PEOPLE_TO_ORDER_MODAL:
    return true
  default:
    return state
  }
}

export const invitePeopleToOrderContext = (state = initialState.invitePeopleToOrderContext, action = {}) => {
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

export const isPlayer = (state = initialState.isPlayer) => {
  return state
}

export const isSetPlayerEmailModalOpen = (state = initialState.isSetPlayerEmailModalOpen, action = {}) => {

  switch (action.type) {
  case CLOSE_SET_PLAYER_EMAIL_MODAL:
    return false
  case OPEN_SET_PLAYER_EMAIL_MODAL:
    return true
  default:
    return state
  }
}

export const player = (state = initialState.player, action = {}) => {
  switch (action.type) {
    case SET_PLAYER_TOKEN:
      return action.payload
    default:
      return state
  }
}

export const isGroupOrdersEnabled = (state = initialState.isGroupOrdersEnabled) => {
  return state
}

export const shouldAskToEnableReusablePackaging = (state = initialState.shouldAskToEnableReusablePackaging, action = {}) => {
  switch (action.type) {
  case STOP_ASKING_ENABLE_REUSABLE_PACKAGING:
    return false
  default:
    return state
  }
}
