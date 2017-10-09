var Chart = require('chart.js');
var _ = require('underscore');

var ctx = document.getElementById("myChart");

var options = {
  scales: {
    yAxes: [{
      ticks: {
        beginAtZero:true
      }
    }]
  }
};

var datasetOptions = {
  fill: false,
  lineTension: 0.1,
  borderCapStyle: 'butt',
  borderDash: [],
  borderDashOffset: 0.0,
  borderJoinStyle: 'miter',
  pointBackgroundColor: "#fff",
  pointBorderWidth: 1,
  pointHoverRadius: 5,
  pointHoverBorderWidth: 2,
  pointRadius: 1,
  pointHitRadius: 10,
  pointHoverBorderColor: "rgba(220,220,220,1)",
  spanGaps: false,
};

var pickupData = _.extend({
  label: "Pickup time (minutes)",
  data: window.AppData.DeliveryTimes.data.pickup,
  backgroundColor: "rgba(41, 128, 185, 0.4)",
  borderColor: "rgba(41, 128, 185, 1)",
  pointBorderColor: "rgba(41, 128, 185, 1)",
  pointHoverBackgroundColor: "rgba(41, 128, 185, 1)",
}, datasetOptions);

var deliveryData = _.extend({
  label: "Delivery time (minutes)",
  data: window.AppData.DeliveryTimes.data.delivery,
  backgroundColor: "rgba(39, 174, 96, 0.4)",
  borderColor: "rgba(39, 174, 96, 1)",
  pointBorderColor: "rgba(39, 174, 96, 1)",
  pointHoverBackgroundColor: "rgba(39, 174, 96, 1)",
}, datasetOptions);

var data = {
  labels: window.AppData.DeliveryTimes.labels,
  datasets: [pickupData, deliveryData]
};

var myLineChart = new Chart(ctx, {
  type: 'line',
  data: data,
  options: options
});
