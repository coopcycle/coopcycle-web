import { guestSlice } from './reduxSlice'

export function buildGuestInitialState(orderNodeId, orderAccessToken) {
  const orderAccessTokens = {}

  if (orderAccessToken) {
    orderAccessTokens[orderNodeId] = orderAccessToken
  }
  return ({
    ...guestSlice.getInitialState(),
    orderAccessTokens: orderAccessTokens,
  })
}
