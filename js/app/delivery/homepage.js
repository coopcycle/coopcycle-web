import AddressBook from './AddressBook'

let prevInitMap
if (window.initMap && typeof window.initMap === 'function') {
  prevInitMap = window.initMap
}

window.initMap = function() {

  new AddressBook(document.querySelector(`#delivery_dropoff_address`), {
    existingAddressControl: document.querySelector(`#delivery_dropoff_address_existingAddress`),
    newAddressControl: document.querySelector(`#delivery_dropoff_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#delivery_dropoff_address_isNewAddress`),
  })

  if (prevInitMap) {
    prevInitMap()
  }
}
