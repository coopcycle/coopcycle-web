import React, { useState } from 'react'
import { useTranslation } from 'react-i18next'

import { Radio } from 'antd'

const radioStyle = {
  display: 'block',
  height: '30px',
  lineHeight: '30px',
}

export default ({ onChange, defaultValue }) => {

  const [ value, setValue ] = useState(defaultValue);
  const { t } = useTranslation()

  return (
    <Radio.Group onChange={ e => {
        setValue(e.target.value)
        onChange(e.target.value)
      }} value={ value }>
      <Radio style={radioStyle} value="GROUP_MODE_FOLDERS">
        { t('ADMIN_DASHBOARD_VIEW_MODE_BY_GROUP') }
      </Radio>
      <Radio style={radioStyle} value="GROUP_MODE_DROPOFF_ASC">
        { t('ADMIN_DASHBOARD_VIEW_MODE_DROPOFF_ASC') }
      </Radio>
      <Radio style={radioStyle} value="GROUP_MODE_DROPOFF_DESC">
        { t('ADMIN_DASHBOARD_VIEW_MODE_DROPOFF_DESC') }
      </Radio>
      <Radio style={radioStyle} value="GROUP_MODE_NONE">
        { t('ADMIN_DASHBOARD_VIEW_MODE_CLASSIC') }
      </Radio>
    </Radio.Group>
  )
}
