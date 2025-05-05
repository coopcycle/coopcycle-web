import React, { useState } from 'react'
import { render } from 'react-dom'

import Modal from 'react-modal'
import store from './address-storage'
import AddressAutosuggest from '../components/AddressAutosuggest'

import { MapContainer, TileLayer, useMap, useMapEvents } from 'react-leaflet'

import ngeohash from 'ngeohash'
import axios from 'axios'


// We used to store a string in "search_address", but now, we want objects
// This function will cleanup legacy behavior
function resolveAddress(form) {

  const addressInput = form.querySelector('input[name="address"]')

  if (addressInput && addressInput.value) {
    return JSON.parse(decodeURIComponent(atob(addressInput.value)))
  }

}


function CenterMarker({ onPositionChange }) {
  const map = useMap();

  const centerMarkerStyle = {
    position: 'absolute',
    top: '50%',
    left: '50%',
    transform: 'translate(-50%, -50%)',
    zIndex: 1000,
    pointerEvents: 'none'
  };

  useMapEvents({
    moveend: () => {
      if (onPositionChange) {
        const { lat, lng } = map.getCenter();
        onPositionChange([lat, lng])
      }
    }
  })

  return (
    <div className="map-center-marker" style={centerMarkerStyle}>
      <div style={{
        width: '25px',
        height: '41px',
        //TODO: Import local icon
        backgroundImage: 'url(https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png)',
        backgroundSize: 'contain',
        backgroundRepeat: 'no-repeat',
        marginLeft: '-12px',
        marginTop: '-41px'
      }} />
    </div>
  );
}

async function _reverseGeocode([lat, lng]) {
  const req = await axios.get(`/search/reverse?lat=${lat}&lng=${lng}`)
  return {
    latitude: lat,
    longitude: lng,
    geo: { latitude: lat, longitude: lng },
    addressLocality: req.data.locality,
    postalCode: req.data.postalCode,
    streetAddress: req.data.address,
    isPrecise: true,
    needsGeocoding: false,
    fromMapPicker: true
  }
}

function MapPicker({ onSelect }) {
  const [isOpen, setIsOpen] = useState(false)
  const mapHeight = window.innerHeight * 0.7

  const [lat, lng] = JSON.parse(document.getElementById('cpccl_settings').dataset.latlng).split(',')
  const [coordinates, setCoordinates] = useState([parseFloat(lat), parseFloat(lng)] || [0, 0])

  return (
    <>
      <div>
        <button onClick={() => setIsOpen(true)} >
          <i className="fa fa-search"></i>
          Search
        </button>
      </div>
      <Modal
        overlayClassName="ReactModal__Overlay--zIndex-1001"
        className="ReactModal__Content--mappicker"
        isOpen={isOpen} >
        <a onClick={() => setIsOpen(false)}>Close</a>
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
          <CenterMarker onPositionChange={coords => { setCoordinates(coords); console.log({ coords }) }} />
        </MapContainer>
        <button onClick={() => {
          // setIsOpen(false)
          onSelect(coordinates)
        }}>OK</button>
      </Modal>
    </>
  )
}

window._paq = window._paq || []

document.querySelectorAll('[data-search="address"]').forEach((container) => {

  const el = container.querySelector('[data-element]')
  const form = container.querySelector('[data-form]')

  if (el) {
    Modal.setAppElement(el)

    const addresses =
      container.dataset.addresses ? JSON.parse(container.dataset.addresses) : []

    const restaurants =
      container.dataset.restaurants ? JSON.parse(container.dataset.restaurants) : []

    render(
      <>
        <MapPicker onSelect={async ([lat, lng]) => {
          const reverse = await _reverseGeocode([lat, lng])
          const geohash = ngeohash.encode(lat, lng, 11)

          form.querySelector('input[name="geohash"]').value = geohash;
          form.querySelector('input[name="address"]').value = btoa(encodeURIComponent(JSON.stringify({
            ...reverse,
            geohash
          })))

          form.submit()
        }} />
        <AddressAutosuggest
          address={resolveAddress(form)}
          addresses={addresses}
          restaurants={restaurants}
          geohash={store.get('search_geohash', '')}
          onClear={() => {
            // clear geohash and address query params but keep others (filters)
            const addressInput = form.querySelector('input[name="address"]')
            const geohashInput = form.querySelector('input[name="geohash"]')

            addressInput.parentNode.removeChild(addressInput)
            geohashInput.parentNode.removeChild(geohashInput)

            const searchParams = new URLSearchParams(window.location.search);
            searchParams.delete('geohash')
            searchParams.delete('address')

            for (const [key, value] of searchParams.entries()) {
              const newInput = document.createElement('input')
              newInput.setAttribute('type', 'hidden')
              newInput.setAttribute('name', key)
              newInput.value = value
              form.appendChild(newInput)
            }

            form.submit()
          }}
          onAddressSelected={(value, address) => {

            console.log(JSON.stringify(address))
            const addressInput = form.querySelector('input[name="address"]')
            const geohashInput = form.querySelector('input[name="geohash"]')

            if (address.geohash !== geohashInput.value) {

              const trackingCategory = container.dataset.trackingCategory
              if (trackingCategory) {
                window._paq.push(['trackEvent', trackingCategory, 'searchAddress', value])
              }

              geohashInput.value = address.geohash
              addressInput.value = btoa(encodeURIComponent(JSON.stringify(address)))

              const searchParams = new URLSearchParams(window.location.search);

              // submit form including existing filters applied
              for (const [key, value] of searchParams.entries()) {
                if (key !== 'geohash' && key !== 'address') {
                  const newInput = document.createElement('input')
                  newInput.setAttribute('type', 'hidden')
                  newInput.setAttribute('name', key)
                  newInput.value = value
                  form.appendChild(newInput)
                }
              }

              form.submit()
            }

          }}
          required={false}
          preciseOnly={false}
          reportValidity={false} /></>, el)
  }

})
