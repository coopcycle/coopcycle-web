import { applyMiddleware, combineReducers, compose, createStore } from 'redux'
import thunk from 'redux-thunk'
import ReduxAsyncQueue from 'redux-async-queue'
import {
  addresses,
  addressFormElements,
  addressModalContext,
  cart,
  cartTiming,
  country,
  datePickerTimeSlotInputName,
  errors,
  invitePeopleToOrderContext,
  isAddressModalOpen,
  isDateModalOpen,
  isFetching,
  isGroupOrdersEnabled,
  isInvitePeopleToOrderModalOpen,
  isMobileCartVisible,
  isNewAddressFormElement,
  isPlayer,
  isProductOptionsModalOpen,
  isSetPlayerEmailModalOpen,
  lastAddItemRequest,
  player,
  productOptionsModalContext,
  restaurant,
  restaurantTiming,
  shouldAskToEnableReusablePackaging,
} from './reducers'
import { playerWebsocket, updateFormElements } from './middlewares'
import { timeRangeSlice } from '../../components/order/timeRange/reduxSlice'
import { apiSlice } from '../../api/slice'
import { accountSlice } from '../../entities/account/reduxSlice'
import { guestSlice } from '../../entities/guest/reduxSlice'
import { orderSlice } from '../../entities/order/reduxSlice'

const middlewares = [
  updateFormElements,
  playerWebsocket,
  thunk,
  ReduxAsyncQueue,
  apiSlice.middleware ]

const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    combineReducers({
      [accountSlice.name]: accountSlice.reducer,
      [guestSlice.name]: guestSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
      isFetching,
      cart,
      [orderSlice.name]: orderSlice.reducer,
      restaurant,
      errors,
      addressFormElements,
      isNewAddressFormElement,
      datePickerTimeSlotInputName,
      isMobileCartVisible,
      addresses,
      lastAddItemRequest,
      restaurantTiming,
      cartTiming,
      isDateModalOpen,
      isAddressModalOpen,
      country,
      isProductOptionsModalOpen,
      productOptionsModalContext,
      addressModalContext,
      isInvitePeopleToOrderModalOpen,
      invitePeopleToOrderContext,
      isPlayer,
      isSetPlayerEmailModalOpen,
      player,
      isGroupOrdersEnabled,
      shouldAskToEnableReusablePackaging,
      [timeRangeSlice.name]: timeRangeSlice.reducer,
    }),
    preloadedState,
    composeEnhancers(
      applyMiddleware(...middlewares),
    ),
  )
}
