import React from 'react'
import { useSelector } from 'react-redux'
import { selectTrailerById } from '../../../shared/src/logistics/redux/selectors'
import Trailer from './icons/Trailer'

export default ({ trailerId }) => {
  const trailer = useSelector(state => selectTrailerById(state, trailerId))
  return (
    <span
      className='ml-2'
      style={{display: 'inline-block', width: '24px', height: '24px', borderRadius: '40px', backgroundColor: trailer?.color || 'red', color: 'white'}}
    >
      <Trailer />
    </span>
  )
}