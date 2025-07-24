import { useCallback, useState } from 'react'
import {
  useDeleteRecurrenceRuleMutation,
  usePatchAddressMutation,
  usePostDeliveryMutation,
  usePostStoreAddressMutation,
  usePutDeliveryMutation,
  usePutRecurrenceRuleMutation,
  useSuggestOptimizationsMutation,
} from '../../../api/slice'
import { useDispatch, useSelector } from 'react-redux'
import {
  selectRejectedSuggestedOrder,
  showSuggestions,
} from '../redux/suggestionsSlice'
import { Mode, modeIn } from '../mode'
import { selectMode } from '../redux/formSlice'
import { useDatadog } from '../../../hooks/useDatadog'
import type { Address, DeliveryFormValues } from '../types'
import { PostDeliveryRequest } from '../../../api/types'

// check if a task ID is temporary (not from backend)
const isTemporaryId = (taskId: string | null): boolean => {
  return taskId !== null && taskId.startsWith('temp-')
}

function serializeAddress(
  address: Address,
): string | { streetAddress: string; latLng: [number, number] } {
  if (Object.prototype.hasOwnProperty.call(address, '@id')) {
    return address['@id'] as string
  }

  return {
    streetAddress: address.streetAddress,
    latLng: [address.geo!.latitude, address.geo!.longitude],
  }
}

function convertValuesToDeliveryPayload(
  storeNodeId: string,
  values: DeliveryFormValues,
): PostDeliveryRequest {
  let data = {
    store: storeNodeId,
    tasks: structuredClone(values.tasks),
    order: structuredClone(values.order),
  } as PostDeliveryRequest

  for (const task of data.tasks) {
    if (isTemporaryId(task['@id'])) {
      delete task['@id']
    }
  }

  if (values.rrule) {
    data = {
      ...data,
      rrule: values.rrule,
    }
  }

  if (values.variantIncVATPrice) {
    data.order.arbitraryPrice = {
      variantName: values.variantName ?? '',
      variantPrice: values.variantIncVATPrice,
    }
  }

  return data
}

function convertDateInRecurrenceRulePayload(value) {
  // Keep only the time part (HH:mm) of the date in the template
  // task[field] - ISO date string

  const date = new Date(value)
  return date.toLocaleTimeString('en-US', {
    hour12: false,
    hour: '2-digit',
    minute: '2-digit',
  })
}

function convertValuesToRecurrenceRulePayload(values) {
  let data = {
    rule: values.rrule,
    template: {
      '@type': 'hydra:Collection',
      'hydra:member': structuredClone(values.tasks).map(task => {
        const address = {
          streetAddress: task.address.streetAddress,
          name: task.address.name,
          telephone: task.address.telephone,
          contactName: task.address.contactName,
        }

        // Preserve the '@id' if it exists
        if ('@id' in task.address) {
          address['@id'] = task.address['@id']
        }

        return {
          type: task.type,
          address: address,
          after: convertDateInRecurrenceRulePayload(task.after),
          before: convertDateInRecurrenceRulePayload(task.before),
          //FIXME; might need to be adjusted when multiple pickups are introduced
          //FIXME: move to the backend; for Delivery entity the same logic is already on the backend side: https://github.com/coopcycle/coopcycle-web/blob/master/src/Api/State/DeliveryProcessor.php#L302-L307
          packages: task.type === 'DROPOFF' ? task.packages : [],
          weight: task.type === 'DROPOFF' ? task.weight : [],
          comments: task.comments,
          tags: task.tags,
        }
      }),
    },
  }

  if (values.variantIncVATPrice) {
    data.arbitraryPriceTemplate = {
      variantName: values.variantName ?? '',
      variantPrice: values.variantIncVATPrice,
    }
  } else {
    data.arbitraryPriceTemplate = null
  }

  return data
}

interface UseSubmitReturn {
  handleSubmit: (values: DeliveryFormValues) => Promise<void>
  error: { isError: boolean; errorMessage: string }
  isSubmitted: boolean
}

export default function useSubmit(
  storeNodeId: string,
  // nodeId: Delivery or RecurrenceRule node
  deliveryNodeId?: string,
  isDispatcher?: boolean,
): UseSubmitReturn {
  const mode = useSelector(selectMode)
  const [error, setError] = useState<{
    isError: boolean
    errorMessage: string
  }>({ isError: false, errorMessage: ' ' })
  const [isSubmitted, setIsSubmitted] = useState<boolean>(false)

  const rejectedSuggestionsOrder = useSelector(selectRejectedSuggestedOrder)

  const [suggestOptimizations] = useSuggestOptimizationsMutation()

  const [createDelivery] = usePostDeliveryMutation()
  const [modifyDelivery] = usePutDeliveryMutation()
  const [modifyRecurrenceRule] = usePutRecurrenceRuleMutation()
  const [deleteRecurrenceRule] = useDeleteRecurrenceRuleMutation()

  const [createAddress] = usePostStoreAddressMutation()
  const [modifyAddress] = usePatchAddressMutation()

  const dispatch = useDispatch()

  const { logger } = useDatadog()

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
      if (mode === Mode.DELIVERY_CREATE) {
        result = await createDelivery(
          convertValuesToDeliveryPayload(storeNodeId, values),
        )
      } else if (mode === Mode.DELIVERY_UPDATE) {
        result = await modifyDelivery({
          nodeId: deliveryNodeId,
          ...convertValuesToDeliveryPayload(storeNodeId, values),
        })
      } else if (mode === Mode.RECURRENCE_RULE_UPDATE) {
        if (values.rrule) {
          result = await modifyRecurrenceRule({
            nodeId: deliveryNodeId,
            ...convertValuesToRecurrenceRulePayload(values),
          })
        } else {
          result = await deleteRecurrenceRule(deliveryNodeId)
        }
      } else {
        logger.error('Unknown mode:', mode)
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

      setIsSubmitted(true)

      if (modeIn(mode, [Mode.DELIVERY_CREATE, Mode.DELIVERY_UPDATE])) {
        const deliveryId = data.id
        const orderId = data.order?.id

        if (isDispatcher) {
          if (orderId) {
            window.location = `/admin/orders/${orderId}`
          } else {
            window.location = `/admin/deliveries/${deliveryId}`
          }
        } else {
          window.location = `/dashboard/deliveries/${deliveryId}`
        }
      } else if (mode === Mode.RECURRENCE_RULE_UPDATE) {
        const storeId = storeNodeId.split('/').pop()
        window.location = `/admin/stores/${storeId}/recurrence-rules`
      }
    },
    [
      storeNodeId,
      deliveryNodeId,
      isDispatcher,
      mode,
      createDelivery,
      modifyDelivery,
      modifyRecurrenceRule,
      deleteRecurrenceRule,
      createAddress,
      modifyAddress,
      checkSuggestionsOnSubmit,
      logger,
    ],
  )

  return { handleSubmit, isSubmitted, error }
}
