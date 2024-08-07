import { fetchBaseQuery } from '@reduxjs/toolkit/dist/query/react'
import {
  selectAccessToken,
  setAccessToken,
} from '../entities/account/reduxSlice'
import { selectOrderAccessToken } from '../entities/guest/selectors'

const guestCheckoutEndpoints = [
  'getOrderValidate',
  'getOrderTiming',
  'updateOrder',
]

const baseQuery = fetchBaseQuery({
  baseUrl: '/',
  prepareHeaders: (headers, { getState, endpoint }) => {
    const accessToken = selectAccessToken(getState())

    if (accessToken) {
      headers.set('Authorization', `Bearer ${accessToken}`)
    } else if (guestCheckoutEndpoints.includes(endpoint)) {
      const orderAccessToken = selectOrderAccessToken(getState())

      if (orderAccessToken) {
        headers.set('Authorization', `Bearer ${orderAccessToken}`)
      }
    }

    return headers
  },
  jsonContentType: 'application/ld+json',
})

//based on https://redux-toolkit.js.org/rtk-query/usage/customizing-queries#automatic-re-authorization-by-extending-fetchbasequery
export const baseQueryWithReauth = async (args, api, extraOptions) => {
  let result = await baseQuery(args, api, extraOptions)

  if (result.error && result.error.status === 401) {
    // try to get a new token; works only for logged in users
    const refreshResponse = await baseQuery(
      window.Routing.generate('profile_jwt'),
      api,
      extraOptions,
    )

    if (refreshResponse.data && refreshResponse.data.jwt) {
      api.dispatch(setAccessToken(refreshResponse.data.jwt))
      // retry the initial query
      result = await baseQuery(args, api, extraOptions)
    } else {
      // api.dispatch(loggedOut())
    }
  }
  return result
}
