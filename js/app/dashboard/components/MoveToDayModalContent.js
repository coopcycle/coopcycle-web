import React from 'react'
import { DatePicker } from 'antd'
import moment from 'moment'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'

import { moveTasksToDay, moveTourToDay } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'

const MoveToDayModalContent = () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()
  const selectedTasks = useSelector(selectSelectedTasks)
  const tour = useSelector(state => state.moveToDayModalTour)

  const [day, setDay] = React.useState(moment().add(1, 'day'))

  const onConfirm = () => {
    const target = moment(day).format()

    if (tour) {
      dispatch(moveTourToDay(tour, target))
    } else {
      dispatch(moveTasksToDay(selectedTasks, target))
    }
  }

  const confirmLabel = tour
    ? t('ADMIN_DASHBOARD_MOVE_TOUR_TO_ANOTHER_DAY_CONFIRM')
    : t('ADMIN_DASHBOARD_MOVE_TO_ANOTHER_DAY_CONFIRM', { count: selectedTasks.length })

  return (
    <div className="px-5 pt-5 pb-5">
      <h4>{t('ADMIN_DASHBOARD_MOVE_TO_ANOTHER_DAY')}</h4>
      <DatePicker
        value={day}
        format="LL"
        onChange={value => setDay(value)}
      />
      <button
        className="btn btn-primary btn-sm ml-3"
        disabled={day === null}
        onClick={onConfirm}>
        {confirmLabel}
      </button>
    </div>
  )
}

export default MoveToDayModalContent
