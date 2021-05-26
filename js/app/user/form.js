import React from 'react'
import { render } from 'react-dom'
import { Switch } from 'antd'

import i18n from '../i18n'
import './form.scss'

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
  $parent.prepend($hidden)

  const checked = $input.is(':checked')

  $input.closest('div.checkbox').remove()

  render(
    <Switch
      defaultChecked={ checked }
      checkedChildren={ i18n.t('USER_EDIT_ENABLED_LABEL') }
      unCheckedChildren={ i18n.t('USER_EDIT_DISABLED_LABEL') }
      onChange={(checked) => {
        if (checked) {
          $parent.append($hidden)
        } else {
          $hidden.remove()
        }
      }}
    />,
    $switch.get(0)
  )

}

$(function() {
  // Render Switch on page load
  $('form[name="update_profile"]').find('.switch').each((index, el) => renderSwitch($(el)))
})
