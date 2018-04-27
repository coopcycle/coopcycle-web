import React from 'react'
import { render } from 'react-dom'
import { DeliveryZonePicker } from "../components/DeliveryZonePicker"

export default (el, options) => {

  options = options || {
    onExprChange: () => {},
    zones: []
  }

  render(
    <DeliveryZonePicker
      zones={options.zones}
      expression={options.expression}
      onExprChange={options.onExprChange}
    />, el)

}
