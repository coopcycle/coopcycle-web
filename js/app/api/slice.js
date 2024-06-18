import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
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
      headers.set('Authorization', `Bearer ${ accessToken }`)

    } else if (guestCheckoutEndpoints.includes(endpoint)) {
      const orderAccessToken = selectOrderAccessToken(getState())

      if (orderAccessToken) {
        headers.set('Authorization', `Bearer ${ orderAccessToken }`)
      }
    }

    return headers
  },
  jsonContentType: 'application/ld+json',
})

//based on https://redux-toolkit.js.org/rtk-query/usage/customizing-queries#automatic-re-authorization-by-extending-fetchbasequery
const baseQueryWithReauth = async (args, api, extraOptions) => {
  let result = await baseQuery(args, api, extraOptions)

  if (result.error && result.error.status === 401) {
    // try to get a new token; works only for logged in users
    const refreshResponse = await baseQuery(window.Routing.generate('profile_jwt'), api, extraOptions)

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

// Define our single API slice object
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: baseQueryWithReauth,
  // The "endpoints" represent operations and requests for this server
  // nodeId is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getOrderTiming: builder.query({
      query: (nodeId) => `${ nodeId }/timing`,
    }),
    getOrderValidate: builder.query({
      query: (nodeId) => `${ nodeId }/validate`,
    }),
    updateOrder: builder.mutation({
      query: ({ nodeId, ...patch }) => ({
        url: nodeId,
        method: 'PUT',
        body: patch,
      }),
    }),
  }),
})

// Export the auto-generated hook for the query endpoints
export const { useGetOrderTimingQuery, useUpdateOrderMutation } = apiSlice
