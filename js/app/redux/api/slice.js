import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react'
import { selectAccessToken } from '../account'
import { selectOrderAccessToken } from '../guest'

const guestCheckoutEndpoints = [
  'getOrderTiming',
  'updateOrder'
]

// Define our single API slice object
// FIXME; implement token refresh
export const apiSlice = createApi({
  reducerPath: 'api',
  baseQuery: fetchBaseQuery({
    baseUrl: '/',
    prepareHeaders: (headers, { getState, endpoint }) => {
      // headers.set('Content-Type', 'application/ld+json') // breaks serialization for PUT requests
      headers.set('Content-Type', 'application/json')

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
  }),
  // The "endpoints" represent operations and requests for this server
  // nodeId is passed in JSON-LD '@id' key, https://www.w3.org/TR/2014/REC-json-ld-20140116/#node-identifiers
  endpoints: builder => ({
    getOrderTiming: builder.query({
      query: (nodeId) => `${ nodeId }/timing`,
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
