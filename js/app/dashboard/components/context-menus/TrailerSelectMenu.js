import React from "react"
import { useDispatch, useSelector } from "react-redux"
import { selectAllTrailers, selectTrailerIdToTaskListIdMap, selectVehicleById } from "../../../../shared/src/logistics/redux/selectors"
import { Item, Menu, useContextMenu } from "react-contexify"
import { useTranslation } from "react-i18next"
import { setTaskListTrailer } from "../../redux/actions"
import { selectIsFleetManagementLoaded } from "../../redux/selectors"

export default ({vehicleId, username, selectTrailerMenuId}) => {

  const { hideAll } = useContextMenu({
    id: selectTrailerMenuId,
  })

  const { t } = useTranslation()
  const vehicle = useSelector(state => selectVehicleById(state, vehicleId))
  const isFleetManagementLoaded = useSelector(selectIsFleetManagementLoaded)
  const trailers = useSelector(selectAllTrailers)
  const trailerIdToTaskListIdMap = useSelector(selectTrailerIdToTaskListIdMap)
  const dispatch = useDispatch()

  const onTrailerClick = ({ data }) => {
    dispatch(setTaskListTrailer(username, data.trailerId))
    hideAll()
  }

  return (
    <Menu id={selectTrailerMenuId}>
      { isFleetManagementLoaded && vehicle ?
        <>
          { vehicle.compatibleTrailers.length > 0 ?
            <>
              {vehicle.compatibleTrailers.map((trailerId, index) => {
                const trailer = trailers.find(t => t['@id'] === trailerId)
                return (
                  <Item
                    onClick={onTrailerClick}
                    data={{trailerId: trailer['@id']}}
                    disabled={trailerIdToTaskListIdMap.has(trailer['@id'])}
                    key={index} >
                      {trailer.name}
                  </Item>)
              })}
              <Item key={-1} onClick={onTrailerClick} data={{trailerId: null}}>{ t('CLEAR') }<i className="fa fa-close ml-1"></i></Item>
            </> :
            <Item key={0} disabled>{ t('ADMIN_DASHBOARD_NO_COMPATIBLE_TRAILER') }</Item>
          }
          </> : null
      }
    </Menu>
  )

}