import AddressBook from './AddressBook'

const el = document.querySelector(`#delivery_dropoff_address`)

if (el) {
  new AddressBook(el, {
    existingAddressControl: document.querySelector(`#delivery_dropoff_address_existingAddress`),
    newAddressControl: document.querySelector(`#delivery_dropoff_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#delivery_dropoff_address_isNewAddress`),
  })
}
