import React from 'react'

import { useDispatch, useSelector } from 'react-redux'
import {
  closeTimeRangeChangedModal,
} from './redux/uiSlice'
import {
  selectIsTimeRangeChangedModalOpen, selectOrderNodeId,
} from './redux/selectors'
import {
  useGetOrderTimingQuery, useUpdateOrderMutation,
} from '../redux/api/slice'
import TimeRangeChangedModal from '../components/order/TimeRangeChangedModal'
import {
  getTimingPathForStorage,
} from '../utils/order/helpers'

export default function RootPage() {
  const isTimeRangeChangedModalOpen = useSelector(
    selectIsTimeRangeChangedModalOpen)

  const dispatch = useDispatch()

  const orderNodeId = useSelector(selectOrderNodeId)

  const {
    data: latestTiming, isFetching,
  } = useGetOrderTimingQuery(orderNodeId, {
    skip: !isTimeRangeChangedModalOpen,
  })

  const [ updateOrder, { isLoading, isError } ] = useUpdateOrderMutation()

  return (<>
    <TimeRangeChangedModal
      isModalOpen={ isTimeRangeChangedModalOpen }
      timing={ latestTiming }
      isFetchingTiming={ isFetching }
      isUpdatingTiming={ isLoading }
      isFailedToUpdateTiming={ isError }
      onChangeTimeRangeClick={ (timeRange) => {
        updateOrder({
          nodeId: orderNodeId, shippingTimeRange: timeRange,
        }).then((result) => {
          if (result.error) {
            //error will be handled via isError prop
            return
          }

          window.sessionStorage.removeItem(getTimingPathForStorage(orderNodeId))
          dispatch(closeTimeRangeChangedModal())

          //refresh page to get updated data in the twig-rendered template
          window.location.reload()
        })
      } }
      onChangeRestaurantClick={ () => {
        dispatch(closeTimeRangeChangedModal())
      } }
    />
  </>)
}
