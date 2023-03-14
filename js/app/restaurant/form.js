import React from 'react'
import { render } from 'react-dom'
import { Switch } from 'antd'
import Dropzone from 'dropzone'
import _ from 'lodash'
import Select from 'react-select'
import 'prismjs'
import 'prismjs/plugins/toolbar/prism-toolbar'
import 'prismjs/plugins/copy-to-clipboard/prism-copy-to-clipboard'

import i18n from '../i18n'
import DropzoneWidget from '../widgets/Dropzone'

import 'prismjs/themes/prism.css'
import 'prismjs/plugins/toolbar/prism-toolbar.css'
import './stripe-connect.scss'

Dropzone.autoDiscover = false

const cuisineAsOption = cuisine => ({
  ...cuisine,
  value: cuisine.id,
  label: cuisine.name
})

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

/**
 * When an element uses the Constraint validation API, but is not visible,
 * Chrome trigger the error "An invalid form control with name='…' is not focusable."
 */

let afterAll

const handleFirstInvalid = function(e) {
  const target = e.target
  const tabPane = target.closest('.tab-pane')
  const anchor = '#' + tabPane.getAttribute('id')

  // Make the tab pane visible, and re-trigger validity
  $(`a[href="${anchor}"]`).tab('show')
  target.reportValidity()

  afterAll = _.once(handleFirstInvalid)
}

afterAll = _.once(handleFirstInvalid)

const onInvalid = function(e) {
  if (!$(e.target).is(':visible')) {
    e.preventDefault()
    _.defer(afterAll, e)
  }
}

// FIXME
// This doesn't work for elements added after page load (like DeliveryZonePicker)
// We would need to use event delegation, but "invalid" event doesn't bubble
// https://stackoverflow.com/questions/18462859/why-is-the-event-listener-for-the-invalid-event-not-being-called-when-using-even
document.querySelector('form[name="restaurant"]')
  .querySelectorAll('input,select,textarea')
  .forEach(el => el.addEventListener('invalid', onInvalid))

/* --- */

$(function() {

  const formData = document.querySelector('#restaurant-form-data')

  // Render Switch on page load
  $('form[name="restaurant"]').find('.switch').each((index, el) => renderSwitch($(el)))

  $('#restaurant_imageFile_delete').closest('.form-group').remove()

  const $formGroup = $('#restaurant_imageFile_file').closest('.form-group')

  $formGroup.empty()

  new DropzoneWidget($formGroup, {
    dropzone: {
      url: formData.dataset.actionUrl,
      params: {
        type: 'restaurant',
        id: formData.dataset.restaurantId
      }
    },
    image: formData.dataset.restaurantImage,
    size: [ 512, 512 ]
  })

  const cuisinesEl = document.querySelector('#cuisines')
  if (cuisinesEl) {

    const cuisines = JSON.parse(cuisinesEl.dataset.values)
    const cuisinesTargetEl = document.querySelector(cuisinesEl.dataset.target)

    render(
      <Select
        defaultValue={ _.map(JSON.parse(cuisinesTargetEl.value || '[]'), cuisineAsOption) }
        isMulti
        options={ _.map(cuisines, cuisineAsOption) }
        onChange={ cuisines => {
          cuisinesTargetEl.value = JSON.stringify(cuisines || [])
        }} />, cuisinesEl)
  }

  $('#restaurant_useDifferentBusinessAddress').on('change', function() {
    if ($(this).is(':checked')) {
      $('#restaurant_businessAddress').closest('.form-group').removeClass('d-none')
      $('#restaurant_businessAddress_streetAddress').attr('required', true)
      setTimeout(() => $('#restaurant_businessAddress_streetAddress').focus(), 350)
    } else {
      $('#restaurant_businessAddress').closest('.form-group').addClass('d-none')
      $('#restaurant_businessAddress_streetAddress').attr('required', false)
    }
  })

  if (!$('#restaurant_useDifferentBusinessAddress').is(':checked')) {
    $('#restaurant_businessAddress').closest('.form-group').addClass('d-none')
    $('#restaurant_businessAddress_streetAddress').attr('required', false)
  }

})


// Delete confirmation
$('#restaurant_delete').on('click', e => {
  if (!window.confirm(i18n.t('CONFIRM_DELETE'))) {
    e.preventDefault()
  }
})
