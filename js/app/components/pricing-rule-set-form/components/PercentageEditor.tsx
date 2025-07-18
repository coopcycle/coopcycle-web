import React, { useState, useRef, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import HelpIcon from '../../../components/HelpIcon'

const convertToUi = value => {
  return value / 100 - 100
}

const convertFromUi = value => {
  return Math.round((parseFloat(value) + 100) * 100)
}

export default ({ defaultValue, onChange }) => {
  const [percentage, setPercentage] = useState(defaultValue.percentage || 10000) // 10000 = 100.00%

  const { t } = useTranslation()

  const initialLoad = useRef(true)

  useEffect(() => {
    if (!initialLoad.current) {
      onChange({
        percentage,
      })
    } else {
      initialLoad.current = false
    }
  }, [percentage, onChange])

  return (
    <div data-testid="price_rule_percentage_editor">
      <label className="mr-2">
        <input
          type="number"
          size="4"
          defaultValue={convertToUi(percentage)}
          step="0.01"
          className="form-control d-inline-block no-number-input-arrow"
          style={{ width: '80px' }}
          onChange={e => {
            setPercentage(convertFromUi(e.target.value))
          }}
        />
        <span className="ml-2">%</span>
        <HelpIcon
          className="ml-2"
          tooltipText={t('PRICE_RANGE_EDITOR.TYPE_PERCENTAGE_HELP')}
        />
      </label>
    </div>
  )
}
