import React from 'react'
import PhoneInput, { isValidPhoneNumber } from 'react-phone-number-input'

import { getCountry } from '../../i18n'

export default (props) => {

  const country = (getCountry() || 'fr').toUpperCase()

  return (
    <PhoneInput
      country={ country }
      showCountrySelect={ false }
      displayInitialValueAsLocalNumber={ true }
      inputClassName="form-control"
      autoComplete="off"
      { ...props } />
  )
}
