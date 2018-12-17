import React from 'react'
import { render } from 'react-dom'
import Switch from 'antd/lib/switch'
import i18n from '../i18n'

function renderSwitch($input) {

  const $parent = $input.closest('div.checkbox').parent()

  const $switch = $('<div class="display-inline-block">')
  const $hidden = $('<input>')

  $switch.addClass('switch')

  $hidden
    .attr('type', 'hidden')
    .attr('name', $input.attr('name'))
    .attr('value', $input.attr('value'))

  $parent.prepend($switch)

  const checked = $input.is(':checked'),
    disabled = $input.is(':disabled')

  if (checked) {
    $parent.prepend($hidden)
  }

  $input.closest('div.checkbox').remove()

  render(
    <Switch defaultChecked={ checked }
      checkedChildren={ i18n.t('ENABLED') }
      unCheckedChildren={ i18n.t('DISABLED') }
      onChange={(checked) => {
        if (checked) {
          $parent.append($hidden)
        } else {
          $hidden.remove()
        }
      }}
      disabled={disabled} />, $switch.get(0)
  )

}


$(function() {
  // Render Switch on page load
  $('form[name="restaurant"]').find('.switch').each((index, el) => renderSwitch($(el)))
  window.CoopCycle.DeliveryZonePicker(
    $('#restaurant_deliveryPerimeterExpression__picker').get(0),
    {
      zones: window.AppData.zones,
      expression: window.AppData.deliveryPerimeterExpression,
      onExprChange: (expr) => { $('#restaurant_deliveryPerimeterExpression').val(expr)}
    }
  )
})
