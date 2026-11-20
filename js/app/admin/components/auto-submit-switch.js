import React from 'react'
import { createRoot } from 'react-dom/client'
import { Switch } from 'antd'

import i18n from '../../i18n'

/**
 * Replaces a server-rendered Bootstrap checkbox with an antd Switch that
 * submits its form as soon as it's flipped -- these toggles persist
 * immediately, there's no separate save button.
 *
 * Symfony's bootstrap_3_layout wraps a checkbox in its own div.checkbox, and
 * that wrapper is what gets swapped out, leaving a hidden input of the same
 * name behind so the usual checkbox POST semantics (present = on, absent =
 * off) still hold.
 */
export default function mountAutoSubmitSwitch(inputSelector) {
  const $input = $(inputSelector)

  if (!$input.length) {
    return
  }

  // Captured before the wrapper is replaced: once $input is detached from
  // the DOM, $input.closest('form') can no longer find it.
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
        $form.get(0).submit()
      }}
    />)
}
