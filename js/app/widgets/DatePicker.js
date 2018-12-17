import React from 'react'
import { render } from 'react-dom'
import DatePicker from 'antd/lib/date-picker'
import LocaleProvider from 'antd/lib/locale-provider'
import fr_FR from 'antd/lib/locale-provider/fr_FR'
import en_GB from 'antd/lib/locale-provider/en_GB'
import moment from 'moment'

const locale = $('html').attr('lang')
const antdLocale = locale === 'fr' ? fr_FR : en_GB

export default function(el, options) {

  options = options || {
    defaultValue: moment(),
    onChange: () => {}
  }

  render(
    <LocaleProvider locale={ antdLocale }>
      <DatePicker
        format={ 'll' }
        defaultValue={ moment(options.defaultValue) }
        onChange={ options.onChange } />
    </LocaleProvider>, el)

}
