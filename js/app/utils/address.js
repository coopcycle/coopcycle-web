import _ from 'lodash'
import i18n from '../i18n'

export const validateForm = (e, searchInput, latInput, lngInput, streetAddrInput) => {

  if (!searchInput.validity.valid) {
    return
  }

  const isValidLatLng = !_.isEmpty(latInput.value) && !_.isEmpty(lngInput.value)
  const isStreetAddressTouched = searchInput.value !== streetAddrInput.value

  if (isStreetAddressTouched || !isValidLatLng) {
    e.preventDefault();

    searchInput.setCustomValidity(i18n.t('PLEASE_SELECT_ADDRESS'))
    if (HTMLInputElement.prototype.reportValidity) {
      searchInput.reportValidity()
    }

    return false
  }

  return true
}
