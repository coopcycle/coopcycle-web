import AddressBook from './AddressBook'
// import { createPackagesWidget } from '../forms/delivery'

// import './embed-start.scss'

// const getDateTimePickerContainer = trigger => trigger.parentNode

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

  // })

  // const packages = document.querySelector(`#delivery_packages`)

  // if (packages) {
  //   const packagesRequired = JSON.parse(packages.dataset.packagesRequired)
  //   createPackagesWidget('delivery', packagesRequired)
  // }
}
