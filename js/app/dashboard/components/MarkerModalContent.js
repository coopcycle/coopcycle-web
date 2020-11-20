import React, { useState } from 'react'
import { withTranslation } from 'react-i18next'
import L from 'leaflet'
import { connect } from 'react-redux'
import { MapContainer, TileLayer, useMapEvents, CircleMarker } from 'react-leaflet'

import { createTask } from '../redux/actions'

const MapMoveHandler = ({ onMove }) => {

  const map = useMapEvents({
    move: () => {
      onMove(map.getCenter())
    },
    moveend: () => {
      onMove(map.getCenter())
    },
  })

  return null
}

const greenOptions = { color: 'green', fillColor: 'green' }

const MarkerModalContent = ({ task, updatePosition }) => {

  const coords = [
    task.address.geo.latitude,
    task.address.geo.longitude
  ]

  const [ centerCoords, setCenterCoords ] = useState(L.latLng(coords))

  return (
    <div>
      <MapContainer center={ coords } zoom={ 16 } scrollWheelZoom={ false } style={{ width: '480px', height: '360px' }}>
        <MapMoveHandler onMove={ newCoords => setCenterCoords(newCoords) } />
        <TileLayer
          attribution='&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy;<a href="https://carto.com/attribution">CARTO</a>'
          url="https://cartodb-basemaps-{s}.global.ssl.fastly.net/rastertiles/voyager/{z}/{x}/{y}.png"
        />
        <CircleMarker
          center={ centerCoords }
          pathOptions={ greenOptions }
          radius={ 10 } />
      </MapContainer>
      <hr />
      <span>{ `${centerCoords.lat}, ${centerCoords.lng}` }</span>
      <button className="btn btn-default" onClick={ () => updatePosition(task, centerCoords) }>Apply</button>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    task: state.lastmile.ui.markerModalTask,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    updatePosition: (task, newCoords) => {
      const newTask = {
        ...task,
        address: {
          ...task.address,
          geo: {
            latitude: newCoords.lat,
            longitude: newCoords.lng,
          }
        }
      }
      dispatch(createTask(newTask))
    },
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(MarkerModalContent))
