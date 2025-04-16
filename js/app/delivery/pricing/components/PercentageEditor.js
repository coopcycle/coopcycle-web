import React, { useState, useRef, useEffect } from 'react'

const convertToUi = value => {
  return value / 100 - 100
}

const convertFromUi = value => {
  return Math.round((parseFloat(value) + 100) * 100)
}

export default ({ defaultValue, onChange }) => {
  const [percentage, setPercentage] = useState(defaultValue.percentage || 10000) // 10000 = 100.00%

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
      </label>
    </div>
  )
}
