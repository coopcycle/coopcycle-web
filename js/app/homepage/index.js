import React from 'react';
import { render } from 'react-dom';
import AddressPicker from "../components/AddressPicker.jsx";

function onPlaceChange (geohash, address) {
    localStorage.setItem('search_geohash', geohash);
    localStorage.setItem('search_address', address);

    window._paq.push(['trackEvent', 'Homepage', 'searchAddress', address]);

    $('#address-search-form').find('input[name=geohash]').val(geohash);
    $('#address-search-form').submit();
}

let address = localStorage.getItem('search_address') || '',
    geohash = localStorage.getItem('search_geohash') || '';

window.initMap = () => {
  render(
    <AddressPicker
      geohash = { geohash }
      address = { address }
      onPlaceChange = { onPlaceChange } />,
    document.getElementById('address-search')
  );
}
