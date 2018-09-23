import React from 'react';
import { render } from 'react-dom';
import AddressPicker from "../address/AddressPicker.jsx";

// suggested addresses for addresspicker
let preferredAddresses = window.AppData.addresses,
    preferredResults = preferredAddresses.map(function (item) { return { suggestion: item.streetAddress, preferred: true }});

let initialGeohash = window.AppData.geohash,
    address = localStorage.getItem('search_address')

$('#restaurant-search-form').find('input[name=geohash]').val(initialGeohash);

function onPlaceChange (geohash, address) {
  if (geohash != initialGeohash) {
    localStorage.setItem('search_geohash', geohash);
    localStorage.setItem('search_address', address);

    window._paq.push(['trackEvent', 'RestaurantList', 'searchAddress', address]);

    $('#restaurant-search-form').find('input[name=geohash]').val(geohash);
    $('#restaurant-search-form').submit();
  }
}

window.initMap = () => {
  render(<AddressPicker
    geohash={ initialGeohash }
    address={ address }
    onPlaceChange={ onPlaceChange }
    preferredResults={ preferredResults } />,
  document.getElementById('address-search'));
}
