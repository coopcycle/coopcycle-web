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
  isTimeRangeChangedModalOpen,
  lastAddItemRequest,
  player,
  productOptionsModalContext,
  restaurant,
  restaurantTiming,
  shouldAskToEnableReusablePackaging,
} from './reducers'
import { accountSlice } from '../../redux/account'
import { guestSlice } from '../../redux/guest'
import { apiSlice } from '../../redux/api/slice'
import { playerWebsocket, updateFormElements } from './middlewares'

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
      account: accountSlice.reducer,
      guest: guestSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
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
      isTimeRangeChangedModalOpen,
    }),
    preloadedState,
    composeEnhancers(
      applyMiddleware(...middlewares),
    ),
  )
}
