import { createSelector } from 'reselect'
import { selectOrderAccessTokens } from './reduxSlice'
import { selectOrderNodeId } from '../order/reduxSlice'

export const selectOrderAccessToken = createSelector(
  selectOrderAccessTokens,
  selectOrderNodeId,
  (orderAccessTokens, orderNodeId) => {
    if (!orderNodeId) {
      return null
    }

    return orderAccessTokens[orderNodeId]
  })
