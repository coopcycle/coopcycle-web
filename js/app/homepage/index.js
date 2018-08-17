import React from 'react';
import { render } from 'react-dom';
import AddressPicker from "../address/AddressPicker.jsx";

function onPlaceChange (geohash, address) {
    localStorage.setItem('search_geohash', geohash);
    localStorage.setItem('search_address', address);

    window._paq.push(['trackEvent', 'Homepage', 'searchAddress', address]);

    $('#address-search-form').find('input[name=geohash]').val(geohash);
    $('#address-search-form').submit();
}

let preferredAddresses = window.AppData.addresses,
    preferredResults = preferredAddresses.map(function (item) { return { suggestion: item.streetAddress, preferred: true }}),
    address = localStorage.getItem('search_address') || '',
    geohash = localStorage.getItem('search_geohash') || '';

window.initMap = () => {
  render(
    <AddressPicker
      geohash = { geohash }
      address = { address }
      onPlaceChange = { onPlaceChange }
      preferredResults = { preferredResults }
    />,
    document.getElementById('address-search')
  );
}
