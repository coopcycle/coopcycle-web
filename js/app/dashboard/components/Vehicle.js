import React from 'react'
import { useSelector } from 'react-redux'
import { selectVehicleById, selectWarehouseById } from '../../../shared/src/logistics/redux/selectors'
import Vehicle from './icons/Vehicle'
import Warehouse from './icons/Warehouse'
import { Tooltip } from 'antd'

export default ({ vehicleId }) => {
    const vehicle = useSelector(state => selectVehicleById(state, vehicleId))
    const warehouse = useSelector(state => selectWarehouseById(state, vehicle?.warehouse['@id']))

    return (
      <>
        { vehicle && warehouse ?
          <>
            <Tooltip title={`${warehouse.name} ${warehouse.address.streetAddress}`}>
              <span
                className='dashboard__badge'
                style={{backgroundColor: vehicle?.color}}
              >
                <Warehouse />
              </span>
            </Tooltip>
            <Tooltip title={`${vehicle.name}`}>
              <span
                className='dashboard__badge dashboard__badge--vehicle'
                style={{backgroundColor: vehicle?.color}}
              >
                <Vehicle />
              </span>
            </Tooltip>
          </>
          : null
        }
      </>
    )
}