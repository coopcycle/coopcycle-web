import { useMemo } from 'react'
import { useFormikContext } from 'formik'

export function useDeliveryFormFormikContext({ taskIndex } = {}) {
  const formik = useFormikContext()
  const { values, errors } = formik

  const isCreateOrderMode = useMemo(() => {
    return !Boolean(values.id)
  }, [values.id])

  const isModifyOrderMode = useMemo(() => {
    return !isCreateOrderMode
  }, [isCreateOrderMode])

  const taskValues = useMemo(() => {
    if (taskIndex !== undefined && taskIndex != null) {
      return values.tasks[taskIndex]
    } else {
      return null
    }
  }, [values.tasks, taskIndex])

  const taskErrors = useMemo(() => {
    if (taskIndex !== undefined && taskIndex != null) {
      return errors.tasks?.[taskIndex]
    } else {
      return null
    }
  }, [errors.tasks, taskIndex])

  const rruleValue = useMemo(() => {
    return values.rrule
  }, [values])

  // Return both the original formik context and your helper functions
  return {
    ...formik,
    isCreateOrderMode,
    isModifyOrderMode,
    taskValues,
    taskErrors,
    rruleValue
  }
}
