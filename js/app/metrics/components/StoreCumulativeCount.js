import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend } from 'chart.js'
import { Line } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Legend)
import moment from 'moment'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'

const Chart = ({ dateRange }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "measures": [
      "Store.cumulativeCount"
    ],
    "timeDimensions": [
      {
        "dimension": "Store.createdAt",
        "granularity": "month",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": {
      "Store.createdAt": "asc"
    },
    "filters": [],
    "dimensions": []
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => moment(c.x).format('MMMM')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Number of stores',
      data: s.series.map((r) => r.value),
      borderColor: COLORS_SERIES[index],
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
    },
  };

  return <Line data={data} options={options} />;
};

export default Chart
