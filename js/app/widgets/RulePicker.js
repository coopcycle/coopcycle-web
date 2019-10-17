import React from 'react'
import { render } from 'react-dom'
import RulePicker from '../components/RulePicker.jsx'

let defaults = {
  zones: [],
  packages: [],
  expression: '',
  onExpressionChange: () => {}
}

export default function(el, options) {

  options = Object.assign(defaults, options)

  render(
    <RulePicker
      zones={options.zones}
      packages={options.packages}
      expression={options.expression}
      onExpressionChange={options.onExpressionChange}
    />,
    el
  )
}
