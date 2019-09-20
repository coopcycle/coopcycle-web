import React from 'react'
import ConfigProvider from 'antd/lib/config-provider'
import { render } from 'react-dom'
import DatePicker from 'antd/lib/date-picker'
import fr_FR from 'antd/es/locale/fr_FR'
import en_GB from 'antd/es/locale/en_GB'
import moment from 'moment'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

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
