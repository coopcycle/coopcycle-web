import React from 'react'
import { createRoot } from 'react-dom/client'
import { DatePicker } from 'antd'
import moment from 'moment'

import { AntdConfigProvider } from '../utils/antd'

export default function(el, options) {

  options = options || {
    defaultValue: moment(),
    onChange: () => {}
  }

  createRoot(el).render(
    <AntdConfigProvider>
      <DatePicker
        format={ 'll' }
        defaultValue={ moment(options.defaultValue) }
        onChange={ options.onChange }
        /* This is needed to work with Bootstrap modal */
        getPopupContainer={ trigger => trigger.parentNode } />
    </AntdConfigProvider>)

}
