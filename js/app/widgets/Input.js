import React from 'react'
import {createRoot} from 'react-dom/client'
import {Input} from 'antd'

import { AntdConfigProvider } from '../utils/antd'

export default function(el, options) {

  options = options || {
    defaultValue: "",
    onChange: () => {}
  }

  createRoot(el).render(
    <AntdConfigProvider>
      <Input {...options} />
    </AntdConfigProvider>)

}
