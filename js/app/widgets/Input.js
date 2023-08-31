import React from 'react'
import {render} from 'react-dom'
import {ConfigProvider, Input} from 'antd'

import {antdLocale} from '../i18n'

export default function(el, options) {

  options = options || {
    defaultValue: "",
    onChange: () => {}
  }

  render(
    <ConfigProvider locale={ antdLocale }>
      <Input {...options} />
    </ConfigProvider>, el)

}
