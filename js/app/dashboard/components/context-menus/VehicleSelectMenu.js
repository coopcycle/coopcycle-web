import React from "react"
import { useDispatch, useSelector } from "react-redux"
import { selectAllVehicles, selectVehicleIdToTaskListIdMap } from "../../../../shared/src/logistics/redux/selectors"
import { Item, Menu, useContextMenu } from "react-contexify"
import { useTranslation } from "react-i18next"
import { setTaskListTrailer, setTaskListVehicle } from "../../redux/actions"

const { hideAll } = useContextMenu({
  id: 'vehicle-selectmenu',
})

export default () => {

  const { t } = useTranslation()
  const vehicles = useSelector(selectAllVehicles)
  const vehicleIdToTaskListIdMap = useSelector(selectVehicleIdToTaskListIdMap)
  const dispatch = useDispatch()

  const onVehicleClick = ({ props, data }) => {
    dispatch(setTaskListVehicle(props.username, data.vehicleId))
    dispatch(setTaskListTrailer(props.username, null))
    hideAll()
  }

  return (
    <Menu id="vehicle-selectmenu">
      {
        vehicles.map((vehicle, index) => {
          return (
            <Item
              onClick={onVehicleClick}
              data={{vehicleId: vehicle['@id']}}
              disabled={vehicleIdToTaskListIdMap.has(vehicle['@id'])}
              key={index} >
                {vehicle.name}
            </Item>)
        })
      }
      <Item key={-1} onClick={onVehicleClick} data={{vehicleId: null}}>{ t('CLEAR') }<i className="fa fa-close ml-1"></i></Item>
    </Menu>
  )

}