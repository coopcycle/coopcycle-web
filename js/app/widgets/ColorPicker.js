import React, { useState } from 'react'
import { render } from 'react-dom'
import SketchPicker from 'react-color/lib/Sketch'

const ColorPicker = ({ value, onChange }) => {

  const [ color, setColor ] = useState(value)

  return (
    <SketchPicker
      color={ color }
      onChangeComplete={ color => {
        setColor(color.hex)
        onChange(color.hex)
      } } />
  )
}

export default function(el) {

  const $div = $('<div>')

  $div.insertAfter($(el))

  // Convert input to hidden
  $(el)
    .detach()
    .attr('type', 'hidden')
    .insertBefore($div)

  render(<ColorPicker value={ $(el).val() || '#fff' } onChange={ hex => $(el).val(hex) } />, $div.get(0))
}
