import React from 'react'
import Impl from '../../../js/app/restaurant/components/MenuNav'
import { AntdConfigProvider } from '../../../js/app/utils/antd'

export default function MenuNav(props) {
  return (
    <AntdConfigProvider>
      <Impl {...props} />
    </AntdConfigProvider>
  )
}
