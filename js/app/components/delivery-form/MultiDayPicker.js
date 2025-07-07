import React from 'react'
import { DatePicker } from 'antd'
import moment from 'moment/moment'
import { timePickerProps } from '../../utils/antd'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'

const MultiDayPicker = ({ taskId }) => {
  const { taskValues, setFieldValue, taskIndex } = useDeliveryFormFormikContext({
    taskId: taskId,
  })

  const handleComplexPickerDateChange = newValues => {
    setFieldValue(`tasks[${taskIndex}].after`, newValues[0].toISOString(true))
    setFieldValue(`tasks[${taskIndex}].before`, newValues[1].toISOString(true))
  }

  return (
    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
      <DatePicker.RangePicker
        style={{ width: '95%' }}
        format={'DD MMMM YYYY HH:mm'}
        // defaultValue={
        //   afterValue && beforeValue
        //     ? [afterValue, beforeValue]
        //     : [defaultAfterValue, defaultBeforeValue]
        // }
        value={[moment(taskValues.after), moment(taskValues.before)]}
        onChange={handleComplexPickerDateChange}
        showTime={{
          ...timePickerProps,
          hideDisabledOptions: true,
        }}
      />
    </div>
  )
}

export default MultiDayPicker
