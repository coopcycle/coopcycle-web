import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import moment from 'moment'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
  scales: {
  }
};

import { getCubeDateRange, getTasksFilters } from '../utils'

const Chart = ({ dateRange, tags }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "measures": [
      "Task.count"
    ],
    "timeDimensions": [
      {
        "dimension": "Task.intervalEndAt",
        "granularity": "day",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "filters": getTasksFilters(tags),
    "order": {
      "Task.date": "asc"
    }
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => moment(c.x).format('ll')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Number of tasks',
      data: s.series.map((r) => r.value),
      backgroundColor: COLORS_SERIES[index],
      fill: false,
    })),
  };
  const options = {
    ...commonOptions,
    legend: {
      display: false
    },
    scales: {
      x: { ...commonOptions.scales.x, stacked: true },
    },
  };
  return <Bar data={data} options={options} />;
};

export default Chart
