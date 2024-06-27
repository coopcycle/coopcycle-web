import React from 'react'
import _ from 'lodash'
import Select from 'react-select'
import { withTranslation } from 'react-i18next'
import { asText } from '../ShippingTimeRange'

const TimeSlotPicker = ({ choices, onChange, t, value }) => {

  const options = choices.map(range => ({
    label: asText(range),
    value: range
  }))

  return (
    <Select
      defaultValue={ _.find(options, o => o.value[0] === value[0] && o.value[1] === value[1]) }
      options={ options }
      placeholder={ t('CART_CHANGE_TIME_MODAL_TITLE') }
      onChange={ ({ value }) => onChange(value) } />
  )
}

export default withTranslation()(TimeSlotPicker)
