import React from "react"
import { useDispatch, useSelector } from "react-redux"
import { selectAllTrailers, selectTrailerIdToTaskListIdMap } from "../../../../shared/src/logistics/redux/selectors"
import { Item, Menu } from "react-contexify"
import { useTranslation } from "react-i18next"
import { setTaskListTrailer } from "../../redux/actions"

export default () => {

  const { t } = useTranslation()
  const trailers = useSelector(selectAllTrailers)
  const trailerIdToTaskListIdMap = useSelector(selectTrailerIdToTaskListIdMap)
  const dispatch = useDispatch()

  const onTrailerClick = ({ props, data }) =>{
    dispatch(setTaskListTrailer(props.username, data.trailerId))
  }

  return (
    <Menu id="trailer-selectmenu">
      <Item key={-1} onClick={onTrailerClick} data={{trailerId: null}}>{ t('CLEAR') }</Item>
      {
        trailers.map((trailer, index) => {
          return (
            <Item
              onClick={onTrailerClick}
              data={{trailerId: trailer['@id']}}
              disabled={trailerIdToTaskListIdMap.has(trailer['@id'])}
              key={index} >
                {trailer.name}
            </Item>)
        })
      }
    </Menu>
  )

}