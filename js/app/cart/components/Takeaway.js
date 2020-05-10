import React from 'react'
import Checkbox from 'antd/lib/checkbox'
import { withTranslation } from 'react-i18next'

import 'antd/es/checkbox/style/index.css'

export default withTranslation()(({ checked, onChange, disabled, defaultChecked, t }) => {

  return (
    <Checkbox
        checked={ checked }
        defaultChecked={ defaultChecked }
        onChange={ e => onChange(e.target.checked) }
        disabled={ disabled }>
      { t('CART_TAKE_AWAY') }
    </Checkbox>
  )
})
