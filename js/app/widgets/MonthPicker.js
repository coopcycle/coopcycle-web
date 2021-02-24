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
        defaultValue={ moment(options.defaultValue) }
        onChange={ options.onChange }
        picker="month" />
    </ConfigProvider>, el)

}
