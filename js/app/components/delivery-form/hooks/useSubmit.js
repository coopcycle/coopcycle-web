import { useCallback, useState } from 'react'
import { useHttpClient } from '../../../user/useHttpClient'
import {
  usePostDeliveryMutation, usePutDeliveryMutation,
} from '../../../api/slice'

const baseURL = location.protocol + '//' + location.host

export default function useSubmit(
  storeId,
  storeNodeId,
  deliveryNodeId,
  isDispatcher,
  isCreateOrderMode
) {
  const { httpClient } = useHttpClient()

  const [error, setError] = useState({ isError: false, errorMessage: ' ' })

  const [createDelivery] = usePostDeliveryMutation()
  const [modifyDelivery] = usePutDeliveryMutation()

  const convertValuesToPayload = useCallback((values) => {
    let data = {
      store: storeNodeId,
      tasks: structuredClone(values.tasks),
    };

    if (values.variantIncVATPrice) {
      data = {
        ...data,
        arbitraryPrice: {
          variantPrice: values.variantIncVATPrice,
          variantName: values.variantName ?? '',
        }
      }
    }

    return data
  }, [storeNodeId])

  const handleSubmit = useCallback(async (values) => {

    let result
    if (isCreateOrderMode) {
      result = await createDelivery(convertValuesToPayload(values))
    } else {
      result = await modifyDelivery({ nodeId: deliveryNodeId, ...convertValuesToPayload(values) })
    }

    const { data, error } = result

    if (error) {
      setError({ isError: true, errorMessage: error.data['hydra:description'] })
      return
    }
    
    const saveAddressUrl = `${baseURL}${storeNodeId}/addresses`
    if (data) {
      for (const task of values.tasks) {
        if (task.saveInStoreAddresses) {
          await httpClient.post(saveAddressUrl, task.address)
          if (error) {
            setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
            return
          }
        }
        if (task.updateInStoreAddresses) {
          await httpClient.patch(`${baseURL}${task.address['@id']}`, task.address)
          if (error) {
            setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
            return
          }
        }
      }

      // TODO : when we are not on the beta URL/page anymore for this form, redirect to document.refferer
      window.location = isDispatcher ? "/admin/deliveries" : `/dashboard/stores/${storeId}/deliveries`
    }
  }, [convertValuesToPayload, storeId, storeNodeId, deliveryNodeId, isDispatcher, httpClient, createDelivery, modifyDelivery, isCreateOrderMode])

  return { handleSubmit, error }
}
