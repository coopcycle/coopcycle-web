import React, { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import moment, { Moment } from 'moment'

import './DateRangePicker.scss'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import SameDayPicker from './SameDayPicker'
import MultiDayPicker from './MultiDayPicker'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'
import { Mode, modeIn } from './mode'

function getNextRoundedTime(): Moment {
  const now = moment()
  now.add(60, 'minutes')
  const roundedMinutes = Math.ceil(now.minutes() / 10) * 10
  if (roundedMinutes >= 60) {
    now.add(1, 'hour')
    now.minutes(roundedMinutes - 60)
  } else {
    now.minutes(roundedMinutes)
  }
  now.seconds(0)

  return now
}

type LabelProps = {
  taskType: 'PICKUP' | 'DROPOFF'
}

const Label = ({ taskType }: LabelProps) => {
  const { t } = useTranslation()

  return taskType === 'DROPOFF' ? (
    <div className="mb-2 font-weight-bold">
      {t('DELIVERY_FORM_DROPOFF_HOUR')}
    </div>
  ) : (
    <div className="mb-2 font-weight-bold">
      {t('DELIVERY_FORM_PICKUP_HOUR')}
    </div>
  )
}

type Props = {
  format: string
  taskId: string
  isDispatcher: boolean
}

const DateTimeRangePicker = ({ format, taskId, isDispatcher }: Props) => {
  const { t } = useTranslation()

  const mode = useSelector(selectMode)

  const { taskValues, setFieldValue, errors, taskIndex: index } = useDeliveryFormFormikContext({
    taskId: taskId,
  })

  useEffect(() => {
    if (!taskValues.after && !taskValues.before) {
      const after = getNextRoundedTime()
      const before = after.clone().add(10, 'minutes')
      setFieldValue(`tasks[${index}].after`, after.toISOString(true))
      setFieldValue(`tasks[${index}].before`, before.toISOString(true))
    }
  }, [taskValues.after, taskValues.before, index, setFieldValue])

  const [isComplexPicker, setIsComplexPicker] = useState(
    moment(taskValues.after).isBefore(taskValues.before, 'day'),
  )

  // When we switch back to simple picker, we need to set back after and before at the same day
  const handleSwitchComplexAndSimplePicker = () => {
    if (isComplexPicker === true) {
      const before = moment(taskValues.after).clone().add(1, 'hours')
      setFieldValue(`tasks[${index}].before`, before.toISOString(true))
    }
    setIsComplexPicker(!isComplexPicker)
  }

  return (
    <>
      <Label taskType={taskValues.type} />
      {isComplexPicker ? (
        <MultiDayPicker taskId={taskId} />
      ) : (
        <SameDayPicker format={format} taskId={taskId} />
      )}
      {isDispatcher &&
        modeIn(mode, [Mode.DELIVERY_CREATE, Mode.DELIVERY_UPDATE]) && (
          <a
            className="text-secondary"
            title={t('SWITCH_COMPLEX_DATEPICKER')}
            onClick={handleSwitchComplexAndSimplePicker}>
            {t('SWITCH_COMPLEX_DATEPICKER')}
          </a>
        )}
      {errors.tasks?.[index]?.before && (
        <div className="text-danger">{errors.tasks[index].before}</div>
      )}
    </>
  )
}

export default DateTimeRangePicker
