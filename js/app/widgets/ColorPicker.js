import React from 'react'
import { render } from 'react-dom'
import { SketchPicker } from 'react-color'

export default function(el) {

  const $div = $('<div>')

  $div.insertAfter($(el))

  // Convert input to hidden
  $(el)
    .detach()
    .attr('type', 'hidden')
    .insertBefore($div)

  render(<SketchPicker
    color={ $(el).val() || '#fff' }
    onChangeComplete={ color => $(el).val(color.hex) } />, $div.get(0))

}
