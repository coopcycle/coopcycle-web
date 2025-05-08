import { useCallback, useState } from 'react'
import { useHttpClient } from '../../../user/useHttpClient'

const baseURL = location.protocol + '//' + location.host

export default function useSubmit(storeId, deliveryId, isDispatcher, storeDeliveryInfos) {
  const { httpClient } = useHttpClient()

  const [error, setError] = useState({ isError: false, errorMessage: ' ' })

  const convertValuesToPayload = useCallback((values) => {
    const infos = {
      store: storeDeliveryInfos["@id"],
      tasks: structuredClone(values.tasks),
    };
    return infos
  }, [storeDeliveryInfos])

  const handleSubmit = useCallback(async (values) => {
    const saveAddressUrl = `${baseURL}/api/stores/${storeId}/addresses`

    const getUrl = (deliveryId) => {
      if (deliveryId) {
        const editDeliveryURL = `${baseURL}/api/deliveries/${deliveryId}`
        return editDeliveryURL
      } else {
        const createDeliveryUrl = `${baseURL}/api/deliveries`
        return createDeliveryUrl
      }
    }

    const createOrEditADelivery = async (deliveryId) => {
      const url = getUrl(deliveryId);
      const method = deliveryId ? 'put' : 'post';
      deliveryId && !isDispatcher
      let data = convertValuesToPayload(values)

      if (values.variantIncVATPrice) {
        data = {
          ...data,
          arbitraryPrice: {
            variantPrice: values.variantIncVATPrice,
            variantName: values.variantName ?? '',
          }
        }
      }

      return await httpClient[method](url, data);
    }

    const { response, error } = await createOrEditADelivery(deliveryId)

    if (error) {
      setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
      return
    }

    if (response) {
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
  }, [convertValuesToPayload, storeId, deliveryId, isDispatcher, httpClient])

  return { handleSubmit, error }
}
