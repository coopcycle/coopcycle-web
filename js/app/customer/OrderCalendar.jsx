import React, { useMemo } from 'react'
import { ResponsiveCalendar } from '@nivo/calendar'

// YlGn ColorBrewer scale: yellow (low) → green (high)
const COLORS = ['#ffffcc', '#c2e699', '#78c679', '#31a354', '#006837']

function formatAmount(cents) {
  return (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export default function OrderCalendar({ data, from, to }) {
  const fromYear = parseInt(from.slice(0, 4), 10)
  const toYear   = parseInt(to.slice(0, 4), 10)
  const numYears = toYear - fromYear + 1
  const height   = numYears * 160 + 60

  const max = useMemo(() => Math.max(...data.map(d => d.value), 1), [data])

  if (data.length === 0) {
    return null
  }

  return (
    <div style={{ height }}>
      <ResponsiveCalendar
        data={data}
        from={from}
        to={to}
        emptyColor="#f0f0f0"
        colors={COLORS}
        minValue={0}
        maxValue={max}
        margin={{ top: 30, right: 20, bottom: 10, left: 30 }}
        yearSpacing={36}
        monthBorderColor="#ffffff"
        monthBorderWidth={2}
        dayBorderWidth={2}
        dayBorderColor="#ffffff"
        tooltip={({ day, value }) => (
          <div style={{
            background: 'white',
            padding: '6px 10px',
            border: '1px solid #ccc',
            borderRadius: 3,
            fontSize: 13,
          }}>
            <strong>{day}</strong><br />
            {formatAmount(value)}
          </div>
        )}
      />
    </div>
  )
}
