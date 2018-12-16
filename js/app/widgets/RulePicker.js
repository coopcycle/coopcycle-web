import React from 'react'
import { render } from 'react-dom'
import RulePicker from '../components/RulePicker.jsx'

export default function(el, options) {

  let defaults = {
    zones: ['Test zone', 'Test2'],
    expression: '',
    onExpressionChange: () => {}
  }

  options = Object.assign(defaults, options)

  render(
    <RulePicker
      zones={options.zones}
      expression={options.expression}
      onExpressionChange={options.onExpressionChange}
    />,
    el
  )
}
