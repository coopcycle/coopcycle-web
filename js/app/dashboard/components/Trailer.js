import React from 'react'
import { useSelector } from 'react-redux'
import { selectTrailerById } from '../../../shared/src/logistics/redux/selectors'
import Trailer from './icons/Trailer'
import { Tooltip } from 'antd'

export default ({ trailerId }) => {
  const trailer = useSelector(state => selectTrailerById(state, trailerId))
  return (<>
    { trailer ?
      <Tooltip title={trailer.name}>
          <span
            className='dashboard__badge dashboard__badge--trailer'
            style={{backgroundColor: trailer.color}}
          >
            <Trailer />
        </span>
      </Tooltip>
      : null
    }
  </>)
}