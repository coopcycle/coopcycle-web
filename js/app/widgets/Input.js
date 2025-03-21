import React from 'react'
import {createRoot} from 'react-dom/client'
import {ConfigProvider, Input} from 'antd'

import {antdLocale} from '../i18n'

export default function(el, options) {

  options = options || {
    defaultValue: "",
    onChange: () => {}
  }

  createRoot(el).render(
    <ConfigProvider locale={ antdLocale }>
      <Input {...options} />
    </ConfigProvider>)

}
