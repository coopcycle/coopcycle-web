import React, { useEffect, useState } from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import L from 'leaflet'
import 'leaflet-providers'
import chroma from 'chroma-js'
import _ from 'lodash'

import { getCubeDateRange } from '../utils'

const el = document.querySelector('#cpccl_settings')
let center
if (el) {
  const [ latitude, longitude ] = JSON.parse(el.dataset.latlng).split(',')
  center = [ parseFloat(latitude), parseFloat(longitude) ]
}

const Chart = ({ dateRange }) => {

  const [ map, setMap ] = useState(null)
  const [ tileLayer, setTileLayer ] = useState(null)

  const { resultSet } = useCubeQuery({
    "dimensions": [
      "CityZone.polygon",
    ],
    "measures": [
      "Order.count",
      "Order.averageTotal"
    ],
    "filters": [
      {
        "member": "Order.state",
        "operator": "equals",
        "values": [
          "fulfilled"
        ]
      }
    ],
    "timeDimensions": [
      {
        "dimension": "Order.shippingTimeRange",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
  });

  useEffect(() => {
    const LMap = L.map('order-count-per-zone-map').setView(center, 11)
    const LTileLayer = L.tileLayer.provider('CartoDB.PositronNoLabels').addTo(LMap)
    setMap(LMap)
    setTileLayer(LTileLayer)
  }, [])

  // https://leafletjs.com/examples/choropleth/

  useEffect(() => {

    if (!resultSet || !map) {
      return
    }

    map.eachLayer(function (layer) {
      if (layer !== tileLayer) {
        map.removeLayer(layer)
      }
    })

    const values = resultSet.tablePivot().map(item => parseInt(item['Order.count'], 10))
    const min = _.min(values)
    const max = _.max(values)

    const colorScale = chroma.scale(['#10ac84', '#feca57']).domain([ min, max ])

    resultSet.tablePivot().forEach(item => {

      const polygon = L.geoJson(JSON.parse(item['CityZone.polygon']), {
        style: {
          fillColor: colorScale(parseInt(item['Order.count'], 10)).hex(),
          weight: 2,
          opacity: 1,
          color: 'white',
          dashArray: '3',
          fillOpacity: 0.7
        },
        onEachFeature: (feature, layer) => {
          layer.on({
            click: (e) => map.fitBounds(e.target.getBounds())
          })
        }
      })

      const tooltip = L.tooltip({
        offset: [ 0, -10 ],
        direction: 'top'
      }).setContent(`
        <span>Number of orders: ${parseInt(item['Order.count'], 10)}</span>
        <br />
        <span>Average order total: ${parseFloat(item['Order.averageTotal'], 10).formatMoney()}</span>
      `)

      polygon.bindTooltip(tooltip)
      polygon.addTo(map)

    })
  }, [resultSet, map, tileLayer])

  return (
    <div className="embed-responsive embed-responsive-16by9 w-100">
      <div className="embed-responsive-item" id="order-count-per-zone-map">
        <Spin />
      </div>
    </div>
  )
};

export default Chart
