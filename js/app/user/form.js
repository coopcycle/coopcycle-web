import React from 'react'
import { render } from 'react-dom'
import { Switch } from 'antd'

import i18n from '../i18n'

import TagsSelect from '../components/TagsSelect'

var tagsEl = document.querySelector('#update_profile_tags');

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

  if (tagsEl) {

    const el = document.createElement('div')
    tagsEl.closest('.form-group').appendChild(el)
  
    tagsEl.classList.add('d-none')
  
    const tags = JSON.parse(tagsEl.dataset.tags)
  
    const defaultValue = tagsEl.value
    render(
      <TagsSelect
        tags={ tags }
        defaultValue={ defaultValue }
        onChange={ tags => {
          const slugs = tags.map(tag => tag.slug)
          tagsEl.value = slugs.join(' ')
        } } />, el)
  }

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
