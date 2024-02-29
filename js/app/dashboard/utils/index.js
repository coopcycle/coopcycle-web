import _ from 'lodash'
import phoneNumberExamples from 'libphonenumber-js/examples.mobile.json'
import { getExampleNumber } from 'libphonenumber-js'

import i18n, { getCountry } from '../../i18n'

export const addressAsText = (address) => {

  if (!_.isEmpty(address.name)) {
    return address.name
  }

  const contactName = address.contactName
  || [
    address.firstName,
    address.lastName,
  ].filter(item => !_.isEmpty(item)).join(' ')
  || null

  const addressParts = [
    address.streetAddress
  ]

  if (contactName) {
    addressParts.unshift(contactName)
  }

  return addressParts.join(' ')
}

const country = (getCountry() || 'fr').toUpperCase()
const phoneNumber = getExampleNumber(country, phoneNumberExamples)

export const phoneNumberExample = i18n.t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_HELP', { example: phoneNumber.formatNational() })

export const getDroppableListStyle = (isDraggingOver) => ({
  background: isDraggingOver ? "lightblue" : "white",
  borderWidth: isDraggingOver ? "4px" : "3px"
})