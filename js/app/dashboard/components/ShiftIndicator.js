import React, { useEffect, useState } from 'react'
import { useSelector } from 'react-redux'
import axios from 'axios'
import { Tooltip } from 'antd'
import { useTranslation } from 'react-i18next'

import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { activityColor } from '../../admin/shift-planning/utils/shiftTypeColor'
import { activityLabel } from '../../admin/shift-planning/utils/activityLabel'
import { sortByStart, wallClockTime } from '../../admin/shift-planning/utils/date'

// The shifts of the day are fetched once, and shared by all the task lists
const cache = new Map()

// The activity catalog rarely changes and is shared by every task list,
// so it's fetched once and cached for the lifetime of the page
let activitiesCache = null

function isEnabled() {
  const el = document.querySelector('#dashboard')

  return el && el.dataset.shiftPlanningEnabled === 'true'
}

function getShifts(jwt, date) {
  if (!cache.has(date)) {
    cache.set(
      date,
      axios
        .get(`/api/shifts?date[after]=${date}&date[before]=${date}`, {
          headers: {
            Authorization: `Bearer ${jwt}`,
            Accept: 'application/ld+json',
          },
        })
        .then(response => response.data['hydra:member'])
        .catch(() => [])
    )
  }

  return cache.get(date)
}

function getShiftActivities(jwt) {
  if (!activitiesCache) {
    activitiesCache = axios
      .get('/api/shift_activities', {
        headers: {
          Authorization: `Bearer ${jwt}`,
          Accept: 'application/ld+json',
        },
      })
      .then(response => response.data['hydra:member'] || [])
      .catch(() => [])
  }

  return activitiesCache
}

export default ({ username }) => {
  const jwt = useSelector(state => state.jwt)
  const date = useSelector(selectSelectedDate).format('YYYY-MM-DD')
  const { t } = useTranslation()

  const [shifts, setShifts] = useState([])
  const [activities, setActivities] = useState([])

  useEffect(() => {
    if (!isEnabled()) {
      return
    }

    let cancelled = false
    getShifts(jwt, date).then(allShifts => {
      if (!cancelled) {
        setShifts(allShifts)
      }
    })
    getShiftActivities(jwt).then(fetchedActivities => {
      if (!cancelled) {
        setActivities(fetchedActivities)
      }
    })

    return () => {
      cancelled = true
    }
  }, [jwt, date])

  const myShifts = sortByStart(
    shifts.filter(shift =>
      shift.assignments.some(
        assignment => assignment.user.username === username,
      ),
    )
  )

  if (myShifts.length === 0) {
    return null
  }

  return (
    <span className="shift-indicator">
      {myShifts.map(shift => (
        <Tooltip
          key={shift['@id']}
          title={activityLabel(shift.activity, activities, t)}>
          <span
            className="badge ml-1"
            style={{
              backgroundColor: activityColor(shift.activity, activities),
              color: 'rgba(0, 0, 0, 0.75)',
            }}>
            {wallClockTime(shift.startsAt)}-{wallClockTime(shift.endsAt)}
          </span>
        </Tooltip>
      ))}
    </span>
  )
}
