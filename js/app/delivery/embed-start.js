import AddressAutosuggest from '../widgets/AddressAutosuggest'
import DateTimePicker from '../widgets/DateTimePicker'

const getDateTimePickerContainer = trigger => trigger.parentNode

window.initMap = function() {

  $.each(['pickup', 'dropoff'], function(index, type) {

    const defaultValue =
      document.querySelector(`#delivery_${type}_doneBefore`).value

    new DateTimePicker(document.querySelector(`#delivery_${type}_doneBefore_widget`), {
      defaultValue,
      getDatePickerContainer: getDateTimePickerContainer,
      getTimePickerContainer: getDateTimePickerContainer,
      onChange: function(date) {
        if (date) {
          document.querySelector(`#delivery_${type}_doneBefore`).value = date.format('YYYY-MM-DD HH:mm:ss')
        }
      }
    })

    const streetAddrInput =
      document.querySelector(`#delivery_${type}_address_streetAddress`)

    new AddressAutosuggest(
      streetAddrInput.closest('.form-group'),
      {
        required: true,
        address: streetAddrInput.value,
        inputId: streetAddrInput.getAttribute('id'),
        inputName: streetAddrInput.getAttribute('name'),
        onAddressSelected: (text, address) => {
          document.querySelector(`#delivery_${type}_address_latitude`).value = address.geo.latitude
          document.querySelector(`#delivery_${type}_address_longitude `).value = address.geo.longitude
          document.querySelector(`#delivery_${type}_address_postalCode`).value = address.postalCode
          document.querySelector(`#delivery_${type}_address_addressLocality`).value = address.addressLocality
        }
      }
    )

  })
}

$('#billing-address').on('hidden.bs.collapse', function () {
  $('#delivery_billingAddress_streetAddress').removeAttr('required')
})

$('#billing-address').on('shown.bs.collapse', function () {
  $('#delivery_billingAddress_streetAddress').attr('required', true)
})

$('#delivery_billingAddress_streetAddress').removeAttr('required')
