import { useCallback, useState } from 'react'
import {
  usePatchAddressMutation,
  usePostDeliveryMutation,
  usePostStoreAddressMutation,
  usePutDeliveryMutation,
  useSuggestOptimizationsMutation,
} from '../../../api/slice'
import { useDispatch, useSelector } from 'react-redux'
import {
  selectRejectedSuggestedOrder,
  showSuggestions,
} from '../redux/suggestionsSlice'

function serializeAddress(address) {
  if (Object.prototype.hasOwnProperty.call(address, '@id')) {
    return address['@id']
  }

  return {
    streetAddress: address.streetAddress,
    latLng: [address.geo.latitude, address.geo.longitude],
  }
}

export default function useSubmit(
  storeId,
  storeNodeId,
  deliveryNodeId,
  isDispatcher,
  isCreateOrderMode,
) {
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })

  const rejectedSuggestionsOrder = useSelector(selectRejectedSuggestedOrder)

  const [suggestOptimizations] = useSuggestOptimizationsMutation()
  const [createDelivery] = usePostDeliveryMutation()
  const [modifyDelivery] = usePutDeliveryMutation()
  const [createAddress] = usePostStoreAddressMutation()
  const [modifyAddress] = usePatchAddressMutation()

  const dispatch = useDispatch()

  const convertValuesToPayload = useCallback(
    values => {
      let data = {
        store: storeNodeId,
        tasks: structuredClone(values.tasks),
      }

      if (values.variantIncVATPrice) {
        data = {
          ...data,
          arbitraryPrice: {
            variantPrice: values.variantIncVATPrice,
            variantName: values.variantName ?? '',
          },
        }
      }

      if (values.rrule) {
        data = {
          ...data,
          rrule: values.rrule,
        }
      }

      if (null !== values.isSavedOrder) {
        data = {
          ...data,
          isSavedOrder: values.isSavedOrder,
        }
      }

      return data
    },
    [storeNodeId],
  )

  const checkSuggestionsOnSubmit = useCallback(
    async values => {
      // no point in checking suggestions for only one pickup and one dropoff task
      if (values.tasks.length < 3) {
        return false
      }

      const body = {
        tasks: structuredClone(values.tasks).map(t => ({
          ...t,
          address: serializeAddress(t.address),
        })),
      }

      const result = await suggestOptimizations(body)

      const { data, error } = result

      if (error) {
        return false
      }

      if (data.suggestions.length === 0) {
        return false
      }

      //The same suggestion was rejected previously
      if (
        rejectedSuggestionsOrder &&
        JSON.stringify(data.suggestions[0].order) ===
          JSON.stringify(rejectedSuggestionsOrder)
      ) {
        return false
      }

      dispatch(showSuggestions(data.suggestions))
      return true
    },
    [dispatch, rejectedSuggestionsOrder, suggestOptimizations],
  )

  const handleSubmit = useCallback(
    async values => {
      const hasSuggestions = await checkSuggestionsOnSubmit(values)
      if (hasSuggestions) {
        // the form will be submitted again after the user accepts or rejects the suggestions (see SuggestionModal)
        return
      }

      let result
      if (isCreateOrderMode) {
        result = await createDelivery(convertValuesToPayload(values))
      } else {
        result = await modifyDelivery({
          nodeId: deliveryNodeId,
          ...convertValuesToPayload(values),
        })
      }

      const { data, error } = result

      if (error) {
        setError({
          isError: true,
          errorMessage: error.data['hydra:description'],
        })
        return
      }

      // Order creation is successful, now we can proceed with secondary items
      if (data) {
        for (const task of values.tasks) {
          if (task.saveInStoreAddresses) {
            const { error } = await createAddress({
              storeNodeId: storeNodeId,
              ...task.address,
            })
            if (error) {
              setError({
                isError: true,
                errorMessage: error.data['hydra:description'],
              })
              return
            }
          }
          if (task.updateInStoreAddresses) {
            const { error } = await modifyAddress({
              nodeId: task.address['@id'],
              ...task.address,
            })
            if (error) {
              setError({
                isError: true,
                errorMessage: error.data['hydra:description'],
              })
              return
            }
          }
        }

        // TODO : when we are not on the beta URL/page anymore for this form, redirect to document.refferer
        window.location = isDispatcher
          ? '/admin/deliveries'
          : `/dashboard/stores/${storeId}/deliveries`
      }
    },
    [
      convertValuesToPayload,
      storeId,
      storeNodeId,
      deliveryNodeId,
      isDispatcher,
      isCreateOrderMode,
      createDelivery,
      modifyDelivery,
      createAddress,
      modifyAddress,
      checkSuggestionsOnSubmit,
    ],
  )

  return { handleSubmit, error }
}
