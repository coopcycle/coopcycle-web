import { useMemo } from 'react'
import { useFormikContext } from 'formik'

export function useDeliveryFormFormikContext({ taskIndex } = {}) {
  const formik = useFormikContext()
  const { values } = formik

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

  const rruleValue = useMemo(() => {
    return values.rrule
  }, [values])

  // Return both the original formik context and your helper functions
  return {
    ...formik,
    isCreateOrderMode,
    isModifyOrderMode,
    taskValues,
    rruleValue
  }
}
