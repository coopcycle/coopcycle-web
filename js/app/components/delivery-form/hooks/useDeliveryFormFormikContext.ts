import { useMemo } from 'react'
import { useFormikContext, FormikContextType } from 'formik'
import { DeliveryFormValues, FormErrors, Task, TaskErrors } from '../types'

// Utility function to find task index by ID
const getTaskIndexById = (tasks: Task[], taskId: string | null): number => {
  if (!taskId || !tasks) return -1
  return tasks.findIndex(task => task['@id'] === taskId)
}

interface UseDeliveryFormFormikContextParams {
  taskId?: string | null
}

interface UseDeliveryFormFormikContextReturn extends FormikContextType<DeliveryFormValues> {
  taskIndex: number
  taskValues: Task | null
  taskErrors: TaskErrors | null
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

  const taskErrors = useMemo((): TaskErrors | null => {
    if (taskIndex !== -1) {
      return (errors as FormErrors).tasks?.[taskIndex] || null
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
