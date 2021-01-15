import React from 'react'
import { render } from 'react-dom'
import { ConfigProvider, DatePicker } from 'antd'
import moment from 'moment'

import { antdLocale } from '../i18n'

export default function(el, options) {

  options = options || {
    defaultValue: moment(),
    onChange: () => {}
  }

  render(
    <ConfigProvider locale={ antdLocale }>
      <DatePicker
        format={ 'll' }
        defaultValue={ moment(options.defaultValue) }
        onChange={ options.onChange }
        /* This is needed to work with Bootstrap modal */
        getCalendarContainer={ trigger => trigger.parentNode } />
    </ConfigProvider>, el)

}
