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

// copy-pasted from https://stackoverflow.com/questions/31790344/determine-if-a-point-reside-inside-a-leaflet-polygon
export const isMarkerInsidePolygon = (marker, polygon) => {
  var polyPoints = polygon.getLatLngs()[0]; // getLatLngs returns an array of array
  var x = marker.getLatLng().lat, y = marker.getLatLng().lng;
  var inside = false;
  for (var i = 0, j = polyPoints.length - 1; i < polyPoints.length; j = i++) {
      var xi = polyPoints[i].lat, yi = polyPoints[i].lng;
      var xj = polyPoints[j].lat, yj = polyPoints[j].lng;

      var intersect = ((yi > y) != (yj > y))
          && (x < (xj - xi) * (y - yi) / (yj - yi) + xi);
      if (intersect) inside = !inside;
  }
  return inside;
}


/**
 * @param {string} slug - Slug we are searching against
 * @param {Array.Object} tags - All tags to be searched
 */
export const findTagFromSlug = (slug, allTags) => {
  return _.find(allTags, t => t.slug === slug)
}
