import React from 'react'
import { createRoot } from 'react-dom/client'
import { DatePicker } from 'antd'
import moment from 'moment'
import qs from 'qs'

import { AntdConfigProvider } from '../utils/antd'

const monthPickerEl = document.querySelector('#month-picker')
const defaultValue  = monthPickerEl.dataset.defaultValue

createRoot(monthPickerEl).render(
  <AntdConfigProvider>
    <DatePicker
      picker="month"
      value={ moment(defaultValue) }
      onChange={ (date, dateString) => {
        window.location.href = window.location.pathname + '?' + qs.stringify({ month: dateString })
      }} />
  </AntdConfigProvider>)

