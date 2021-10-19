import AddressBook from './AddressBook'

import './homepage.scss'

const el = document.querySelector(`#delivery_tasks_1_address`)

if (el) {
  new AddressBook(el, {
    existingAddressControl: document.querySelector(`#delivery_tasks_1_address_existingAddress`),
    newAddressControl: document.querySelector(`#delivery_tasks_1_address_newAddress_streetAddress`),
    isNewAddressControl: document.querySelector(`#delivery_tasks_1_address_isNewAddress`),
  })
}
