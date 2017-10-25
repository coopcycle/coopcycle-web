import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';
import RestaurantListFilter from "./RestaurantListFilter.jsx";

moment.locale('fr');


// suggested addresses for addresspicker
let preferredAddresses = window.AppData.addresses,
    preferredResults = preferredAddresses.map(function (item) { return { suggestion: item.streetAddress, preferred: true }});

let searchDate = moment(window.AppData.searchDate),
    initialGeohash = window.AppData.geohash,
    address = localStorage.getItem('search_address'),
    searchDateString = searchDate.format('YYYY-MM-DD HH:mm:ss');

// generate dateTime ranges
let minutes = moment().minutes(),
    nextQuarter = (Math.trunc(minutes / 15) + 1) * 15,
    start = moment().startOf('hour').minutes(nextQuarter),
    current = moment().add(1, 'hour').startOf('hour').minutes(nextQuarter),
    availabilities = [],
    nextDay = moment().add(1, 'day').endOf('day');


while (current.isBefore(nextDay)) {
  if (current.hour() > 7) {
    availabilities.push(current.format('YYYY-MM-DD HH:mm:ss'));
  }
  current = current.add(15, 'minutes');
}

// if searchDate is before the datepicker start,
// the customer wants to be delivered the sooner
let initialDateString = start.isAfter(searchDate) ? '' : searchDateString  ;


$('#restaurant-search-form').find('input[name=geohash]').val(initialGeohash);
$('#restaurant-search-form').find('input[name=datetime]').val(initialDateString);

function onDatePickerChange (dateString) {
  if (dateString != initialDateString) {
    localStorage.setItem('search__date', dateString);
    $('#restaurant-search-form').find('input[name=datetime]').val(dateString);
    $('#restaurant-search-form').submit();
  }
}

function onPlaceChange (geohash, address) {
  if (geohash != initialGeohash) {
    localStorage.setItem('search_geohash', geohash);
    localStorage.setItem('search_address', address);
    $('#restaurant-search-form').find('input[name=geohash]').val(geohash);
    $('#restaurant-search-form').submit();
  }
}

render(<RestaurantListFilter
          onPlaceChange={ onPlaceChange }
          geohash={ initialGeohash }
          address={ address }
          preferredResults = { preferredResults }

          onDatePickerChange={(date) => onDatePickerChange(date)}
          initialDate={ initialDateString }
          availabilities={ availabilities }
       />,
       document.getElementById('restaurant-list-filter'));
