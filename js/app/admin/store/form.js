import React from 'react'
import { createRoot } from 'react-dom/client'
import TagsSelect from '../../components/TagsSelect'
import { addressMapper } from '../../widgets/addressForm'
import i18n from '../../i18n'
import DeliveryZonePicker from '../../components/DeliveryZonePicker'

var tagsEl = document.querySelector('#store_tags');

if (tagsEl) {

  const el = document.createElement('div')
  tagsEl.closest('.form-group').appendChild(el)

  tagsEl.classList.add('d-none')

  const tags = JSON.parse(tagsEl.dataset.tags)
  const defaultValue = tagsEl.value

  createRoot(el).render(
    <TagsSelect
      tags={ tags }
      defaultValue={ defaultValue }
      onChange={ tags => {
        const slugs = tags.map(tag => tag.slug)
        tagsEl.value = slugs.join(' ')
      } } />
  )
}

$('#address-form-modal').on('show.bs.modal', function (event) {
  var modal = $(this)
  var button = $(event.relatedTarget) // Button that triggered the modal
  var address = button.data('address')
  var newAddress = button.data('newAddress')
  var addressObj = button.data('addressObj')

  if (address && addressObj) {

    var streetAddress = button.data('streetAddress')
    var name = button.data('name')
    var description = button.data('description')
    var telephone = button.data('telephone')
    var contactName = button.data('contactName')

    modal.find('form input[type="search"]').val(streetAddress)

    // Map form fields
    addressMapper(modal.find('form input[type="search"]').get(0), addressObj)

    modal.find('#address_name').val(name)
    modal.find('#address_description').val(description)
    modal.find('#address_telephone').val(telephone)
    modal.find('#address_contactName').val(contactName)

    modal.find('form').attr('action', address)
  } else {
    modal.find('#address_name').val('')
    modal.find('#address_description').val('')
    modal.find('#address_telephone').val('')
    modal.find('#address_contactName').val('')

    modal.find('form').attr('action', newAddress)
  }
})

$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
  window.location.hash = e.target.getAttribute('aria-controls')
})

if (window.location.hash !== '') {
  $(`a[aria-controls="${window.location.hash.substring(1)}"]`).tab('show')
}

const timeSlotsEl = document.querySelector('#store_timeSlots')
const timeSlotEl = document.querySelector('#store_timeSlot')

if (timeSlotsEl && timeSlotEl) {

  const defaultValue = timeSlotEl.value

  timeSlotsEl.querySelectorAll('input[type="checkbox"]').forEach(chk => {

    if (chk.value === defaultValue) {
      document.querySelector(chk.dataset.defaultControl).checked = true
    }

    document.querySelector(chk.dataset.defaultControl).addEventListener('change', e => {
      if (e.target.checked) {
        timeSlotEl.value = e.target.value
      }
    })

    chk.addEventListener('change', (e) => {

      const defaultControl = document.querySelector(e.target.dataset.defaultControl)
      const checkedCheckboxes = Array.from(timeSlotsEl.querySelectorAll('input[type="checkbox"]:checked'))
      const checkedRadios = Array.from(timeSlotsEl.querySelectorAll('input[type="radio"]:checked'))

      if (!e.target.checked) {

        if (defaultControl.checked) {
          if (checkedCheckboxes.length > 0) {
            const firstChecked = checkedCheckboxes[0]
            document.querySelector(firstChecked.dataset.defaultControl).checked = true
          }
        }

        defaultControl.setAttribute('disabled', true)
        defaultControl
          .closest('.radio')
          .classList.add('disabled')
        defaultControl.checked = false

      } else {

        defaultControl.removeAttribute('disabled')
        defaultControl
          .closest('.radio')
          .classList.remove('disabled')

        if (checkedRadios.length === 0) {
          defaultControl.checked = true
        }
      }

      const checkedRadio = timeSlotsEl.querySelector('input[type="radio"]:checked')
      if (checkedRadio) {
        timeSlotEl.value = checkedRadio.value
      } else {
        timeSlotEl.value = ''
      }
    })
  })

  document.querySelector('#store_timeSlot').closest('.form-group').classList.add('d-none')
}


// Delete confirmation
$('#store_delete').on('click', e => {
  if (!window.confirm(i18n.t('CONFIRM_DELETE'))) {
    e.preventDefault()
  }
})

document.querySelectorAll('[data-widget="delivery-perimeter-expression"]').forEach(el => {

  const input = el.querySelector('input[type="hidden"]')

  if (input) {

    const container = document.createElement('div')

    createRoot(container).render(
      <DeliveryZonePicker
        zones={ JSON.parse(el.dataset.zones) }
        expression={ el.dataset.defaultValue }
        onExprChange={ expr => $(input).val(expr) }
      />
    )

    el.appendChild(container)
  }
})
