import React from 'react'
import { useSelector } from 'react-redux'
import { selectVehicleById } from '../../../shared/src/logistics/redux/selectors'

export default ({ vehicleId }) => {
    const vehicle = useSelector(state => selectVehicleById(state, vehicleId))
    return <span
        style={{display: 'inline-block', width: '20px', height: '20px', backgroundColor: vehicle?.color || 'red', color: 'white'}}
        >
            {vehicleId}
        </span>
}