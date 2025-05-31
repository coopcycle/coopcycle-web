import React, { useState, useEffect } from 'react'
import Spinner from '../core/Spinner'
import L from 'leaflet'
import { MapContainer, TileLayer, Marker, useMap } from 'react-leaflet'
import MapHelper from '../../MapHelper'
import 'leaflet/dist/leaflet.css'
import 'leaflet-arrowheads'
require('beautifymarker')
import DeliveryResume from './DeliveryResume'
import { taskTypeColor, taskTypeMapIcon } from '../../styles'

const createMarkerIcon = (icon, iconShape, color) => {
  return L.BeautifyIcon.icon({
    icon: icon,
    iconShape: iconShape,
    borderColor: color,
    textColor: color,
    backgroundColor: 'transparent',
  })
}

const CustomMarker = ({ position, icon, iconShape, color }) => {
  const markerIcon = createMarkerIcon(icon, iconShape, color)

  return <Marker position={position} icon={markerIcon} />
}

const ArrowheadPolyline = ({ positions, options }) => {
  const map = useMap()

  useEffect(() => {
    if (!map) return

    const polyline = L.polyline(positions, options).addTo(map)
    polyline.arrowheads()

    return () => {
      map.removeLayer(polyline)
    }
  }, [positions, options, map])

  return null
}

const FitBoundsToMarkers = ({ positions, maxZoom = 17 }) => {
  const map = useMap()

  useEffect(() => {
    if (Array.isArray(positions) && positions.length > 0) {
      const bounds = L.latLngBounds(positions.map(pos => pos.latLng))
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom })
      }
    } else if (
      positions &&
      typeof positions.latitude === 'number' &&
      typeof positions.longitude === 'number'
    ) {
      const bounds = L.latLngBounds([[positions.latitude, positions.longitude]])
      if (bounds.isValid()) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom })
      }
    }
  }, [map, positions, maxZoom])
}

export default ({ storeDeliveryInfos, tasks }) => {
  const [storeGeo, setStoreGeo] = useState(null)
  const [deliveryGeo, setDeliveryGeo] = useState([])
  const [deliveryRoute, setDeliveryRoute] = useState('')
  const [distance, setDistance] = useState({ kms: 0 })

  useEffect(() => {
    if (storeDeliveryInfos.address) {
      setStoreGeo({
        latitude: storeDeliveryInfos.address.geo.latitude,
        longitude: storeDeliveryInfos.address.geo.longitude,
      })
    }
  }, [storeDeliveryInfos])

  useEffect(() => {
    const allLatLng = tasks
      .map(task => ({
        latLng: [task.address.geo?.latitude, task.address.geo?.longitude],
        type: task.type,
      }))
      .filter(item => item.latLng[0] && item.latLng[1])

    // update deliveryGeo ONLY if it has changed
    if (JSON.stringify(allLatLng) !== JSON.stringify(deliveryGeo)) {
      setDeliveryGeo(allLatLng)
    }

  }, [tasks, deliveryGeo, setDeliveryGeo])

  useEffect(() => {
    const latLngArray = deliveryGeo.map(item => item.latLng)

    if (latLngArray.length > 1) {
      MapHelper.route(latLngArray).then(route => {
        const distance = parseInt(route.distance, 10)
        const kms = (distance / 1000).toFixed(2)
        const decodeRoute = MapHelper.decodePolyline(route.geometry)
        const coordinates = decodeRoute.map(coord => [coord.lat, coord.lng])
        setDeliveryRoute(coordinates)
        setDistance({ kms })
      })
    }

  }, [deliveryGeo])

  return (
    <>
      {storeGeo ? (
        <>
          <MapContainer
            className="mb-3"
            scrollWheelZoom={false}
            style={{ height: '250px', width: '100%' }}>
            <TileLayer
              url="https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png"
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
              subdomains="abcd"
              maxZoom={19}
            />

            {deliveryGeo.length > 0
              ? deliveryGeo.map((geo, index) => (
                  <CustomMarker
                    key={index}
                    position={geo.latLng}
                    icon={taskTypeMapIcon(geo.type)}
                    iconShape="marker"
                    color={taskTypeColor(geo.type)}
                  />
                ))
              : null}
            {deliveryGeo.length > 1 ? (
              <ArrowheadPolyline
                positions={deliveryRoute}
                options={{
                  color: '#3498DB',
                }}
              />
            ) : null}
            {deliveryGeo.length > 0 ? (
              <FitBoundsToMarkers positions={deliveryGeo} />
            ) : (
              <FitBoundsToMarkers positions={storeGeo} />
            )}
          </MapContainer>
          <DeliveryResume distance={distance.kms} tasks={tasks} />
        </>
      ) : (
        <Spinner />
      )}
    </>
  )
}
