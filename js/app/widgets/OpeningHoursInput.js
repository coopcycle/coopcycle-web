import React from 'react'
import { render } from 'react-dom'
import OpeningHours from '../components/OpeningHours'
import _ from 'lodash'

export default function(el, options) {

  options = options || {}

  const template = el.getAttribute('data-prototype')

  const addRow = (index, value) => {
    const $input = $(template.replace(/__name__/g, index))
      .attr('data-opening-hour', index)
    if (!value) {
      value = '00:00-23:59'
    }
    $input.val(value)
    $(el).append($input)
  }

  const value = _.map(el.querySelectorAll('input[type="hidden"]'), input => input.getAttribute('value'))

  render(<OpeningHours
    value={ value }
    locale={ options.locale }
    rowsWithErrors={ options.rowsWithErrors }
    onLoad={rows => _.each(rows, (value, index) => addRow(index, value))}
    onChange={rows => {
      _.each(rows, (value, index) => el.querySelector(`[data-opening-hour="${index}"]`).setAttribute('value', value))
      if (typeof options.onChange === 'function') options.onChange(rows)
    }}
    onRowAdd={() => {
      const index = el.querySelectorAll('[data-opening-hour]').length
      addRow(index)
      if (typeof options.onRowAdd === 'function') options.onRowAdd()
    }}
    onRowRemove={index => {
      el.querySelector(`[data-opening-hour="${index}"]`).remove()
      const values = _.map(el.querySelectorAll('[data-opening-hour]'), input => input.getAttribute('value'))
      el.querySelectorAll('[data-opening-hour]').forEach(input => input.remove())
      _.each(values, (value, index) => addRow(index, value))
      if (typeof options.onRowRemove === 'function') options.onRowRemove(index)
    }} />, el)
}
