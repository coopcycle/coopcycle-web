import React from 'react'
import Checkbox from 'antd/lib/checkbox'
import { withTranslation } from 'react-i18next'

import 'antd/es/checkbox/style/index.css'

export default withTranslation()(({ checked, onChange, disabled }) => {

  return (
    <Checkbox checked={ checked } onChange={ e => onChange(e.target.checked) } disabled={ disabled }>
      Retrait sur place
    </Checkbox>
  )
})
