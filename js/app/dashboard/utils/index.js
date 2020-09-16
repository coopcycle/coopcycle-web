import _ from 'lodash'

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
