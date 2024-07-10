import React from 'react'
import { useSelector } from 'react-redux'
import { selectVehicleById } from '../../../shared/src/logistics/redux/selectors'
import Vehicle from './icons/Vehicle'
import Warehouse from './icons/Warehouse'

export default ({ vehicleId }) => {
    const vehicle = useSelector(state => selectVehicleById(state, vehicleId))
    return (
      <>
        <span
          className='mx-2'
          style={{display: 'inline-block', width: '24px', height: '24px', borderRadius: '40px', backgroundColor: vehicle?.color || 'red', color: 'white'}}
        >
          <Warehouse />
        </span>
        <span
          style={{display: 'inline-block', width: '24px', height: '24px', borderRadius: '40px', backgroundColor: vehicle?.color || 'red', color: 'white'}}
        >
          <Vehicle />
        </span>
      </>
    )
}