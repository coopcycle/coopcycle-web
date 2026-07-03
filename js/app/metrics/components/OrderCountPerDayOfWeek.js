import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import moment from 'moment'
import _ from 'lodash'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'

const Chart = ({ dateRange }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "measures": [
      "Order.count"
    ],
    "timeDimensions": [
      {
        "dimension": "Order.shippingTimeRange",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": [
      [
        "Order.dayOfWeek",
        "asc"
      ]
    ],
    "dimensions": [
      "Order.dayOfWeek"
    ],
    "filters": [
      {
        "member": "Order.state",
        "operator": "equals",
        "values": [
          "fulfilled"
        ]
      }
    ]
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  // Make it work for days with zero orders
  const labels = []
  for (let d = 1; d <= 7; d++) {
    labels.push(moment().isoWeekday(d).format('dddd'))
  }

  console.log(resultSet.series())

  const data = {
    labels,
    datasets: resultSet.series().map((s, index) => ({
      label: s.title,
      data: labels.map((label, index) => {
        const isoWeekday = index + 1
        const r = _.find(s.series, s => parseInt(s.x, 10) === isoWeekday)
        return r ? r.value : 0
      }),
      backgroundColor: COLORS_SERIES[index],
      fill: false,
    })),
  };

  const options = {
    ...commonOptions,
    plugins: {
      legend: {
        display: false
      },
    },
    scales: {
      x: {
        stacked: true,
      },
      y: {
        stacked: true,
        ticks: {
          precision: 0,
        },
      },
    },
  };
  return <Bar data={data} options={options} />;
};

export default Chart
