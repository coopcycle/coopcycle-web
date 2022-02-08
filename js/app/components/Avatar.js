import React from 'react'
import L from 'leaflet'

// To clear the cache, we append the sha1 of the commit when the avatars were changed
// https://github.com/coopcycle/coopcycle-web/commit/d7f4e3ba6fed57fba19199926773c4ab1059083c
const sha1 = 'd7f4e3ba6fed57fba19199926773c4ab1059083c'

export default (props) => {

  return (
    <img src={ window.Routing.generate('user_avatar', { username: props.username, t: sha1 }) }
      width={ props.size || 20 } height={ props.size || 20 } />
  )
}

export const createLeafletIcon = username => {

  const iconUrl = window.Routing.generate('user_avatar', { username, t: sha1 })

  return L.icon({
    iconUrl: iconUrl,
    iconSize:    [20, 20], // size of the icon
    iconAnchor:  [10, 10], // point of the icon which will correspond to marker's location
    popupAnchor: [-2, -72], // point from which the popup should open relative to the iconAnchor,
  })
}
