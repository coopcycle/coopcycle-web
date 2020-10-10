import AddressBook from './AddressBook'

const el = document.querySelector(`#delivery_dropoff_address`)

if (el) {
  let prevInitMap
  if (window.initMap && typeof window.initMap === 'function') {
    prevInitMap = window.initMap
  }

  window.initMap = function() {

    new AddressBook(el, {
      existingAddressControl: document.querySelector(`#delivery_dropoff_address_existingAddress`),
      newAddressControl: document.querySelector(`#delivery_dropoff_address_newAddress_streetAddress`),
      isNewAddressControl: document.querySelector(`#delivery_dropoff_address_isNewAddress`),
    })

    if (prevInitMap) {
      prevInitMap()
    }
  }
}
