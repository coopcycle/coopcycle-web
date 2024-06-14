export const selectOrderAccessToken = state => {
  let orderNodeId = null

  if (state.cart) {
    // restaurant menu page
    orderNodeId = state.cart['@id']
  } else if (state.order) {
    // checkout flow (Address, Payment)
    orderNodeId = state.order['@id']
  }

  if (!orderNodeId) {
    return null
  }

  return state.guest.orderAccessTokens[orderNodeId]
}
