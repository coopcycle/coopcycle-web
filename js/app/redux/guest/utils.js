import { initialState } from './slice'

export function getGuestInitialState(orderNodeId, orderAccessToken) {
  const orderAccessTokens = {}

  if (orderAccessToken) {
    orderAccessTokens[orderNodeId] = orderAccessToken
  }
  return ({
    ...initialState,
    orderAccessTokens: orderAccessTokens,
  })
}
