import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import _ from 'lodash'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'

const Chart = ({ dateRange }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "dimensions": [
      "Order.hourRange"
    ],
    "timeDimensions": [
      {
        "dimension": "Order.shippingTimeRange",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": [
      [
        "Order.hourRange",
        "asc"
      ]
    ],
    "measures": [
      "Order.count"
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

  const labels = []
  for (let h = 0; h < 24; h++) {
    labels.push(`${_.padStart(h, 2, '0')}:00 - ${_.padStart(h + 1, 2, '0')}:00`)
  }

  const data = {
    labels,
    datasets: resultSet.series().map((s, index) => ({
      label: s.title,
      data: labels.map((label) => {
        const r = _.find(s.series, s => s.x === label)
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
