import React from 'react'
import { DatePicker } from 'antd'
import moment from 'moment'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'

import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { moveTasksToDay, moveTourToDay } from '../redux/actions'
import { selectSelectedTasks } from '../redux/selectors'

const MoveToDayModalContent = () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()
  const selectedTasks = useSelector(selectSelectedTasks)
  const selectedDate = useSelector(selectSelectedDate)
  const tour = useSelector(state => state.moveToDayModalTour)

  const nextDay = moment(selectedDate).add(1, 'day')

  const [day, setDay] = React.useState(nextDay)

  const moveToDay = target => {
    const formatted = moment(target).format()

    if (tour) {
      dispatch(moveTourToDay(tour, formatted))
    } else {
      dispatch(moveTasksToDay(selectedTasks, formatted))
    }
  }

  const confirmLabel = tour
    ? t('ADMIN_DASHBOARD_MOVE_TOUR_TO_ANOTHER_DAY_CONFIRM')
    : t('ADMIN_DASHBOARD_MOVE_TO_ANOTHER_DAY_CONFIRM', { count: selectedTasks.length })

  return (
    <div className="px-5 pt-5 pb-5">
      <h4>{t('ADMIN_DASHBOARD_MOVE_TO_ANOTHER_DAY')}</h4>
      <div className="mb-3">
        <button
          className="btn btn-default btn-sm"
          onClick={() => moveToDay(nextDay)}>
          <i className="fa fa-arrow-right mr-2"></i>
          {t('ADMIN_DASHBOARD_MOVE_TO_NEXT_DAY')}
        </button>
      </div>
      <DatePicker
        value={day}
        format="LL"
        onChange={value => setDay(value)}
      />
      <button
        className="btn btn-primary btn-sm ml-3"
        disabled={day === null}
        onClick={() => moveToDay(day)}>
        {confirmLabel}
      </button>
    </div>
  )
}

export default MoveToDayModalContent
