import React from 'react';
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import moment from 'moment'

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
        "granularity": "day",
        "dateRange": getCubeDateRange(dateRange),
      }
    ],
    "order": {
      "Order.shippingTimeRange": "asc"
    },
    "filters": [
      {
        "member": "Order.reusablePackagingEnabled",
        "operator": "equals",
        "values": [
          "1"
        ]
      },
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

  const data = {
    labels: resultSet.categories().map((c) => moment(c.x).format('ll')),
    datasets: resultSet.series().map((s) => ({
      label: s.title,
      data: s.series.map((r) => r.value),
      backgroundColor: '#FF6492',
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
