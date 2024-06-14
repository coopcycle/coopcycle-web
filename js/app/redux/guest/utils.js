import { initialState } from './slice'

export function getGuestInitialState(orderNodeId, orderAccessToken) {
  const orderAccessTokens = {}

  const hasAccount = window._auth && window._auth.isAuth

  if (!hasAccount && orderAccessToken) {
    orderAccessTokens[orderNodeId] = orderAccessToken
  }
  return ({
    ...initialState,
    orderAccessTokens: orderAccessTokens,
  })
}
