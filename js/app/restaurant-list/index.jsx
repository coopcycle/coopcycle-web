import React from 'react';
import RestaurantListFilter from "./RestaurantListFilter.jsx";
import { render } from 'react-dom';
import moment from 'moment';

moment.locale('fr');

let searchDate = moment(window.AppData.searchDate),
    searchDateString = searchDate.format('YYYY-MM-DD HH:mm:ss');

// generate dateTime ranges
let minutes = moment().minutes(),
    nextQuarter = (Math.trunc(minutes / 15) + 1) * 15,
    start = moment().startOf('hour').minutes(nextQuarter),
    current = moment().startOf('hour').minutes(nextQuarter),
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


function onDatePickerChange (dateString) {
  if (dateString != initialDateString) {
    $('#restaurant-search-form').find('input[name=datetime]').val(dateString);
    $('#restaurant-search-form').submit();
  }
}

render(<RestaurantListFilter
          onDatePickerChange={(date) => onDatePickerChange(date)}
          initialDate={ initialDateString }
          availabilities={ availabilities }
       />,
       document.getElementById('restaurant-list-filter'));
