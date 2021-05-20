import React from 'react'
import { render } from 'react-dom'
import { ConfigProvider, DatePicker } from 'antd'
import moment from 'moment'
import qs from 'qs'

import { antdLocale } from '../i18n'

const monthPickerEl = document.querySelector('#month-picker')
const defaultValue  = monthPickerEl.dataset.defaultValue

render(
  <ConfigProvider locale={ antdLocale }>
    <DatePicker
      picker="month"
      value={ moment(defaultValue) }
      onChange={ (date, dateString) => {
        window.location.href = window.location.pathname + '?' + qs.stringify({ month: dateString })
      }} />
  </ConfigProvider>, monthPickerEl)

