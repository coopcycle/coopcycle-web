import React, { forwardRef } from 'react'
import PhoneInput from 'react-phone-number-input/input'

import { getCountry } from '../../i18n'

const InputComponent = forwardRef((props, ref) => {

  const propsWithClassName = {
    ...props,
    className: 'form-control'
  }

  return (
    <input ref={ ref } { ...propsWithClassName } />
  )
})

export default (props) => {

  const country = (getCountry() || 'fr').toUpperCase()

  return (
    <PhoneInput
      country={ country }
      inputComponent={ InputComponent }
      autoComplete="off"
      { ...props } />
  )
}
