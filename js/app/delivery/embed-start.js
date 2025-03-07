import moment from 'moment'
import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import { createPackagesWidget } from '../forms/delivery'

import './embed-start.scss'

const getDateTimePickerContainer = trigger => trigger.parentNode

const taskForms = Array
  .from(document.querySelectorAll('[data-form="task"]'))

taskForms.forEach(function(el) {

  const doneBeforeEl = document.querySelector(`#${el.id}_doneBefore`)

  if (doneBeforeEl) {
    new DateTimePicker(document.querySelector(`#${el.id}_doneBefore_widget`), {
      defaultValue: doneBeforeEl.value || moment().format('YYYY-MM-DD HH:mm:ss'),
      getDatePickerContainer: getDateTimePickerContainer,
      getTimePickerContainer: getDateTimePickerContainer,
      onChange: function(date) {
        if (date) {
          document.querySelector(`#${el.id}_doneBefore`).value = date.format('YYYY-MM-DD HH:mm:ss')
        }
      }
    })
  }

  new AddressBook(document.querySelector(`#${el.id}_address`), {
    existingAddressControl: document.querySelector(`#${el.id}_address_existingAddress`),
    newAddressControl: document.querySelector(`#${el.id}_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#${el.id}_address_isNewAddress`),
  })

  const packages = document.querySelector(`#${el.id}_packages`)

  if (packages) {
    const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
    createPackagesWidget(el, packagesRequired)
  }

})

function setBillingAddressRequired(required) {
  if (required) {
    $('#delivery_billingAddress_streetAddress').prop('required', true)
  } else {
    $('#delivery_billingAddress_streetAddress').prop('required', false)
    $('#delivery_billingAddress_streetAddress').removeAttr('required')
  }
}

$('#billing-address').on('hidden.bs.collapse', function () {
  setBillingAddressRequired(false)
})

$('#billing-address').on('shown.bs.collapse', function () {
  setBillingAddressRequired(true)
})

setBillingAddressRequired(false)
