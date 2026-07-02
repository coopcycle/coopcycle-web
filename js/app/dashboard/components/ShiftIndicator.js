import React, { useEffect, useState } from 'react'
import { useSelector } from 'react-redux'
import axios from 'axios'
import { Tooltip } from 'antd'

import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { shiftTypeColor } from '../../admin/shift-planning/utils/shiftTypeColor'
import { wallClockTime } from '../../admin/shift-planning/utils/date'

// The shifts of the day are fetched once, and shared by all the task lists
const cache = new Map()

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

export default ({ username }) => {
  const jwt = useSelector(state => state.jwt)
  const date = useSelector(selectSelectedDate).format('YYYY-MM-DD')

  const [shifts, setShifts] = useState([])

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

    return () => {
      cancelled = true
    }
  }, [jwt, date])

  const myShifts = shifts.filter(shift =>
    shift.assignments.some(assignment => assignment.user.username === username)
  )

  if (myShifts.length === 0) {
    return null
  }

  return (
    <span className="shift-indicator">
      {myShifts.map(shift => (
        <Tooltip key={shift['@id']} title={shift.type}>
          <span
            className="badge ml-1"
            style={{
              backgroundColor: shiftTypeColor(shift.type),
              color: 'rgba(0, 0, 0, 0.75)',
            }}>
            {wallClockTime(shift.startsAt)}-{wallClockTime(shift.endsAt)}
          </span>
        </Tooltip>
      ))}
    </span>
  )
}
