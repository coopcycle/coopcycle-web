import React from 'react'
import { createRoot } from 'react-dom/client'
import OrderCalendar from './OrderCalendar'

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('customer-order-calendar')
  if (!el) return

  const data = JSON.parse(el.dataset.days)
  const from = el.dataset.from
  const to   = el.dataset.to

  createRoot(el).render(
    <OrderCalendar data={data} from={from} to={to} />
  )
})
