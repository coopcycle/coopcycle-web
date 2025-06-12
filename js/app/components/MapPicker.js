import React, { useState } from 'react'

import { MapContainer, TileLayer, useMap, useMapEvents } from 'react-leaflet'
import MarkerIcon from 'leaflet/dist/images/marker-icon.png'

import { useTranslation } from 'react-i18next'

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
    zIndex: 1002,
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
          backgroundImage: `url(${MarkerIcon})`,
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

export default function MapPicker({ onSelect, onClose = () => { }, isOpen }) {
  const { t } = useTranslation()
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
      overlayClassName="ReactModal__Overlay ReactModal__Overlay--zIndex-2001"
      className="ReactModal__Content--mappicker"
      onRequestClose={onClose}
      isOpen={isOpen}>
      <h3 className="mb-3">{t('SELECT_YOUR_LOCATION')}</h3>
      <MapContainer
        className="mb-3"
        center={coordinates}
        zoom={16}
        scrollWheelZoom={true}
        style={{
          height: `${mapHeight}px`,
          width: '100%',
          border: '1px solid #ccc',
          borderRadius: '8px',
        }}>
        <TileLayer
          url="https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png"
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
          subdomains="abcd"
          maxZoom={19}
        />
        <CenterMarker onPositionChange={setCoordinates} />
      </MapContainer>
      <button
        className="btn btn-primary float-right my-2"
        onClick={async () => {
          setLoading(true)
          const address = await reverseGeocode(coordinates)
          setLoading(false)
          onSelect(address)
        }}
        disabled={isLoading}>
        {t('CONFIRM_LOCATION')}
      </button>
    </Modal>
  )
}
