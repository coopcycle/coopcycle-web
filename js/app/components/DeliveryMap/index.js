import React, { useEffect, useMemo, useState } from 'react'
import L from 'leaflet'
import 'leaflet-arrowheads'
require('beautifymarker')
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet'
import { taskTypeColor, taskTypeMapIcon } from '../../styles'
import MapHelper from '../../MapHelper'
import { useTranslation } from 'react-i18next'

import './Map.scss'

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

export default ({ defaultAddress, tasks }) => {
  const defaultGeo = useMemo(() => {
    if (defaultAddress) {
      return {
        latitude: defaultAddress.geo.latitude,
        longitude: defaultAddress.geo.longitude,
      }
    }
    return null
  }, [defaultAddress])

  const [deliveryGeo, setDeliveryGeo] = useState([])
  const [deliveryRoute, setDeliveryRoute] = useState('')
  const [distance, setDistance] = useState({ kms: 0 })

  const { t } = useTranslation()

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
    <div className="embed-responsive embed-responsive-4by3">
      <MapContainer className="embed-responsive-item" scrollWheelZoom={false}>
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
          <FitBoundsToMarkers positions={defaultGeo} />
        )}
      </MapContainer>
      <div className="map-distance-overlay p-2">
        <span className="font-weight-bold" data-testid="delivery-distance">
          {t('ADMIN_DASHBOARD_DISTANCE', { distance: distance.kms })}
        </span>
      </div>
    </div>
  )
}
