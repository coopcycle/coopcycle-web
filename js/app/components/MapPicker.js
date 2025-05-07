import React, { useState } from 'react'
import { Button } from 'antd'

import { MapContainer, TileLayer, useMap, useMapEvents } from 'react-leaflet'

import Modal from 'react-modal'
import ngeohash from 'ngeohash'
import axios from 'axios'

function CenterMarker({ onPositionChange }) {
  const map = useMap()

  const centerMarkerStyle = {
    position: 'absolute',
    top: '50%',
    left: '50%',
    transform: 'translate(-50%, -50%)',
    zIndex: 1000,
    pointerEvents: 'none',
  }

  useMapEvents({
    moveend: () => {
      if (onPositionChange) {
        const { lat, lng } = map.getCenter()
        onPositionChange([lat, lng])
      }
    },
  })

  return (
    <div className="map-center-marker" style={centerMarkerStyle}>
      <div
        style={{
          width: '25px',
          height: '41px',
          //TODO: Import local icon
          backgroundImage:
            'url(https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png)',
          backgroundSize: 'contain',
          backgroundRepeat: 'no-repeat',
          marginLeft: '-12px',
          marginTop: '-41px',
        }}
      />
    </div>
  )
}

async function reverseGeocode([lat, lng]) {
  const req = await axios.get(`/search/reverse?lat=${lat}&lng=${lng}`)
  return {
    latitude: lat,
    longitude: lng,
    geo: { latitude: lat, longitude: lng },
    geohash: ngeohash.encode(lat, lng, 11),
    addressLocality: req.data.locality,
    postalCode: req.data.postalCode,
    streetAddress: req.data.address,
    isPrecise: true,
    needsGeocoding: false,
    isMapPicked: true,
  }
}

export default function MapPicker({ onSelect, isOpen }) {
  const [isLoading, setLoading] = useState(false)

  const mapHeight = window.innerHeight * 0.7

  const [coordinates, setCoordinates] = useState(() => {
    const element = document.getElementById('cpccl_settings')
    if (!element) return [0, 0]

    try {
      const coordString = JSON.parse(element.dataset.latlng)
      const [lat, lng] = coordString.split(',').map(s => s.trim())
      return [parseFloat(lat), parseFloat(lng)]
    } catch {
      return [0, 0]
    }
  })

  return (
    <Modal
      overlayClassName="ReactModal__Overlay--zIndex-1001"
      className="ReactModal__Content--mappicker"
      isOpen={isOpen}>
      <h2>Map</h2>
      <MapContainer
        className="mb-3"
        center={coordinates}
        zoom={16}
        scrollWheelZoom={true}
        style={{ height: `${mapHeight}px`, width: '100%' }}>
        <TileLayer
          url="https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
          subdomains="abcd"
          maxZoom={19}
        />
        <CenterMarker onPositionChange={setCoordinates} />
      </MapContainer>
      <Button
        onClick={async () => {
          setLoading(true)
          const address = await reverseGeocode(coordinates)
          setLoading(false)
          onSelect(address)
        }}
        loading={isLoading}
        type="primary">
        OK
      </Button>
    </Modal>
  )
}
