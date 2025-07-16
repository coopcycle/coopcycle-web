import { useMemo } from 'react'
import { useFormikContext } from 'formik'

// Utility function to find task index by ID
const getTaskIndexById = (tasks, taskId) => {
  if (!taskId || !tasks) return -1
  return tasks.findIndex(task => task['@id'] === taskId)
}

export function useDeliveryFormFormikContext({ taskId } = {}) {
  const formik = useFormikContext()
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

  const taskErrors = useMemo(() => {
    if (taskIndex !== -1) {
      return errors.tasks?.[taskIndex]
    } else {
      return null
    }
  }, [errors.tasks, taskIndex])

  const rruleValue = useMemo(() => {
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
