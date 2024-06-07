export async function getTiming(orderId, orderAccessToken) {
  const httpClient = new window._auth.httpClient();

  const hasAccount = window._auth && window._auth.isAuth
  const hasOrderAccessToken = orderAccessToken !== null

  if (!hasAccount && !hasOrderAccessToken) {
    return
  }

  // guest checkout
  if (!hasAccount && hasOrderAccessToken) {
    httpClient.setToken(orderAccessToken);
  }

  const url = window.Routing.generate("api_orders_get_cart_timing_item", { id: orderId });
  const result = await httpClient.get(url);
  return result.response;
}
