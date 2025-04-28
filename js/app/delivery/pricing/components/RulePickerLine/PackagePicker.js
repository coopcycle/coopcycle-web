import React from 'react'
import { useSelector } from 'react-redux'
import { selectPackages } from '../../redux/pricingSlice'

export default function PackagePicker({ value, onChange }) {
  const packages = useSelector(selectPackages)

  return (
    <select onChange={onChange} value={value} className="form-control input-sm">
      <option value="">-</option>
      {packages.map((item, index) => {
        return (
          <option value={item} key={index}>
            {item}
          </option>
        )
      })}
    </select>
  )
}
