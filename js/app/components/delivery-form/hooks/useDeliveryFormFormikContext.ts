import { useMemo } from 'react'
import { useFormikContext, FormikContextType, FormikErrors } from 'formik'
import type { DeliveryFormValues } from '../types'
import { TaskPayload } from '../../../api/types'

// Utility function to find task index by ID
const getTaskIndexById = (tasks: TaskPayload[], taskId: string | null): number => {
  if (!taskId || !tasks) return -1
  return tasks.findIndex(task => task['@id'] === taskId)
}

type UseDeliveryFormFormikContextParams = {
  taskId?: string | null
}

type UseDeliveryFormFormikContextReturn = FormikContextType<DeliveryFormValues> & {
  taskIndex: number
  taskValues: TaskPayload | null
  taskErrors: FormikErrors<TaskPayload> | null
  rruleValue: string | undefined
}

export function useDeliveryFormFormikContext({ taskId }: UseDeliveryFormFormikContextParams = {}): UseDeliveryFormFormikContextReturn {
  const formik = useFormikContext<DeliveryFormValues>()
  const { values, errors } = formik

  // Determine the actual task index to use
  const taskIndex = useMemo(() => {
    if (taskId) {
      return getTaskIndexById(values.tasks, taskId)
    }
    return -1
  }, [values.tasks, taskId])

  const taskValues = useMemo(() => {
    if (taskIndex !== -1) {
      return values.tasks[taskIndex]
    } else {
      return null
    }
  }, [values.tasks, taskIndex])

  const taskErrors = useMemo((): FormikErrors<TaskPayload> | null => {
    if (taskIndex !== -1) {
      return errors.tasks?.[taskIndex] || null
    } else {
      return null
    }
  }, [errors, taskIndex])

  const rruleValue = useMemo((): string | undefined => {
    return values.rrule
  }, [values])

  return {
    ...formik,
    taskIndex,
    taskValues,
    taskErrors,
    rruleValue,
  }
}
