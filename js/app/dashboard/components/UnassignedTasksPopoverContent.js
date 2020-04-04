import React, { useState } from 'react'

import { Radio } from 'antd'

const radioStyle = {
  display: 'block',
  height: '30px',
  lineHeight: '30px',
}

export default ({ t, onChange, defaultValue }) => {

  const [ value, setValue ] = useState(defaultValue);

  return (
    <Radio.Group onChange={ e => {
        setValue(e.target.value)
        onChange(e.target.value)
      }} value={ value }>
      <Radio style={radioStyle} value="GROUP_MODE_FOLDERS">
        { t('ADMIN_DASHBOARD_VIEW_MODE_BY_GROUP') }
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
