import React from 'react'
import { useSelector } from 'react-redux'
import { selectZones } from '../../redux/pricingSlice'

export default function ZonePicker({ value, onChange }) {
  const zones = useSelector(selectZones)

  return (
    <select onChange={onChange} value={value} className="form-control input-sm">
      <option value="">-</option>
      {zones.map((item, index) => {
        return (
          <option value={item} key={index}>
            {item}
          </option>
        )
      })}
    </select>
  )
}
