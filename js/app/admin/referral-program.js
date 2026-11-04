import React from 'react'
import { createRoot } from 'react-dom/client'
import { Switch } from 'antd'

import i18n from '../i18n'

import '../bootstrap-reset.scss'

function mountSwitch($input) {

  // Captured before the wrapper is replaced below -- once $input is
  // detached from the DOM, $input.closest('form') can no longer find it.
  const $form = $input.closest('form')
  const $wrapper = $input.closest('.checkbox')
  const $parent = $wrapper.parent()

  const $switch = $('<div class="d-inline-block">')
  const $hidden = $('<input>')
    .attr('type', 'hidden')
    .attr('name', $input.attr('name'))
    .attr('value', $input.attr('value') || '1')

  const checked = $input.is(':checked')

  $wrapper.replaceWith($switch)

  if (checked) {
    $parent.append($hidden)
  }

  createRoot($switch.get(0)).render(
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
        // Mirrors the previous onchange="this.form.submit()" behaviour --
        // the switch persists immediately, there's no separate save button.
        $form.get(0).submit()
      }}
    />)
}

$(function() {
  const $input = $('#referral_program_active_form_active')
  if ($input.length) {
    mountSwitch($input)
  }
})
