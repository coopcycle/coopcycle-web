import { useCallback, useState } from 'react'
import {
  usePatchAddressMutation,
  usePostDeliveryMutation,
  usePostStoreAddressMutation,
  usePutDeliveryMutation,
} from '../../../api/slice'

export default function useSubmit(
  storeId,
  storeNodeId,
  deliveryNodeId,
  isDispatcher,
  isCreateOrderMode,
) {
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })

  const [createDelivery] = usePostDeliveryMutation()
  const [modifyDelivery] = usePutDeliveryMutation()
  const [createAddress] = usePostStoreAddressMutation()
  const [modifyAddress] = usePatchAddressMutation()

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

  const handleSubmit = useCallback(
    async values => {
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
    ],
  )

  return { handleSubmit, error }
}
