import React, { useState, useEffect } from 'react'
import L from 'leaflet'
import { MapContainer, TileLayer, Marker, Polyline } from 'react-leaflet'
import MapHelper from '../../MapHelper'
import 'leaflet/dist/leaflet.css'

import icon from 'leaflet/dist/images/marker-icon.png'
import iconShadow from 'leaflet/dist/images/marker-shadow.png'

let DefaultIcon = L.icon({
  iconUrl: icon,
  shadowUrl: iconShadow,
})

L.Marker.prototype.options.icon = DefaultIcon

export default ({ storeDeliveryInfos, tasks }) => {
  const [storeGeo, setStoreGeo] = useState(null)
  const [deliveryGeo, setDeliveryGeo] = useState([])
  const [deliveryRoute, setDeliveryRoute] = useState('')

  console.log('route', deliveryRoute)

  console.log('map', tasks)
  console.log(deliveryGeo)

  useEffect(() => {
    if (storeDeliveryInfos.address) {
      setStoreGeo({
        latitude: storeDeliveryInfos.address.geo.latitude,
        longitude: storeDeliveryInfos.address.geo.longitude,
      })
    }
  }, [storeDeliveryInfos])

  // je fais un map des tasks pour avoir un array avec seulement les coordonnées des taches pour générer mes markers

  useEffect(() => {
    const geo = tasks.map(task => task.address.geo).filter(Boolean)
    const allLatLng = []

    geo.forEach(g => {
      const latLong = [g.latitude, g.longitude]
      allLatLng.push(latLong)
    })

    MapHelper.route(allLatLng).then(route => {
      // const distance = parseInt(route.distance, 10)
      // const kms = (distance / 1000).toFixed(2)
      const decodeRoute = MapHelper.decodePolyline(route.geometry)
      const coordinates = decodeRoute.map(coord => [coord.lat, coord.lng])
      setDeliveryRoute(coordinates)

      // return {
      //   distance,
      //   kms,

      // }
    })
    setDeliveryGeo(allLatLng)
  }, [tasks])

  return (
    <>
      {storeGeo ? (
        <MapContainer
          center={[storeGeo.latitude, storeGeo.longitude]}
          zoom={12}
          scrollWheelZoom={false}
          style={{ height: '250px', width: '100%' }}>
          <TileLayer
            attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
          />
          {deliveryGeo.length > 0
            ? deliveryGeo.map((geo, index) => (
                <Marker key={index} position={geo} />
              ))
            : null}
          {deliveryGeo.length > 0 ? (
            <Polyline positions={deliveryRoute} />
          ) : null}
        </MapContainer>
      ) : (
        <div>Loading</div>
      )}
    </>
  )
}
