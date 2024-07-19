import React from "react"
import { useDispatch, useSelector } from "react-redux"
import { selectAllTrailers, selectTrailerIdToTaskListIdMap, selectVehicleById } from "../../../../shared/src/logistics/redux/selectors"
import { Item, Menu, useContextMenu } from "react-contexify"
import { useTranslation } from "react-i18next"
import { setTaskListTrailer } from "../../redux/actions"

export default ({vehicleId, username}) => {

  const submenuId = `trailer-selectmenu-${username}`
  const { hideAll } = useContextMenu({
    id: submenuId,
  })

  const { t } = useTranslation()
  const vehicle = useSelector(state => selectVehicleById(state, vehicleId))
  const compatibleTrailerIds = vehicle.compatibleTrailers.map(item => item.trailer)
  const trailers = useSelector(selectAllTrailers)
  const trailerIdToTaskListIdMap = useSelector(selectTrailerIdToTaskListIdMap)
  const dispatch = useDispatch()

  const onTrailerClick = ({ props, data }) =>{
    dispatch(setTaskListTrailer(props.username, data.trailerId))
    hideAll()
  }

  return (
    <Menu id={submenuId}>-
      { compatibleTrailerIds.length > 0 ?
        <>
          <Item key={-1} onClick={onTrailerClick} data={{trailerId: null}}>{ t('CLEAR') }</Item>
          {compatibleTrailerIds.map((trailerId, index) => {
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
        </> :
        <Item key={0} disabled>{ t('NO_COMPATIBLE_TRAILER') }</Item>
      }
    </Menu>
  )

}