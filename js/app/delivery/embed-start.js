import AddressBook from '../delivery/AddressBook'
import DateTimePicker from '../widgets/DateTimePicker'
import { createPackagesWidget } from '../forms/delivery'

import './embed-start.scss'

const getDateTimePickerContainer = trigger => trigger.parentNode

window.initMap = function() {

  $.each(['pickup', 'dropoff'], function(index, type) {

    const doneBeforeEl = document.querySelector(`#delivery_${type}_doneBefore`)

    if (doneBeforeEl) {
      new DateTimePicker(document.querySelector(`#delivery_${type}_doneBefore_widget`), {
        defaultValue: doneBeforeEl.value,
        getDatePickerContainer: getDateTimePickerContainer,
        getTimePickerContainer: getDateTimePickerContainer,
        onChange: function(date) {
          if (date) {
            document.querySelector(`#delivery_${type}_doneBefore`).value = date.format('YYYY-MM-DD HH:mm:ss')
          }
        }
      })
    }

    new AddressBook(document.querySelector(`#delivery_${type}_address`), {
      existingAddressControl: document.querySelector(`#delivery_${type}_address_existingAddress`),
      newAddressControl: document.querySelector(`#delivery_${type}_address_newAddress_streetAddress`),
      isNewAddressControl: document.querySelector(`#delivery_${type}_address_isNewAddress`),
    })

  })

  const packages = document.querySelector(`#delivery_packages`)

  if (packages) {
    const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
    createPackagesWidget('delivery', packagesRequired)
  }
}

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
