import React, { useState, useEffect } from 'react'
import { Select, Skeleton, Checkbox } from 'antd'

function AvatarRenderer({ value }) {
  return (
    <span>
      <img src={'/images/avatars/' + value + '.png'} width="20px" />
      <span className="ml-2">{value}</span>
    </span>
  )
}

export default ({ onChange, queryFn }) => {
  const [assignCourier, setAssignCourier] = useState(false)
  const { data, isLoading } = queryFn({ skip: !assignCourier })

  useEffect(() => {
    if (!assignCourier) {
      onChange(null)
    }
  }, [assignCourier])

  const options = isLoading
    ? []
    : data?.['hydra:member']?.map(({ username }) => ({ value: username })) || []

  return (
    <>
      <Checkbox
        checked={assignCourier}
        onChange={e => setAssignCourier(e.target.checked)}>
        Assign a courier
      </Checkbox>
      {assignCourier && (
        <Select
          className="w-100 mt-2"
          showSearch
          onChange={({ value }) => onChange(value)}
          loading={isLoading}
          optionRender={AvatarRenderer}
          labelRender={AvatarRenderer}
          options={options}
        />
      )}
    </>
  )
}
