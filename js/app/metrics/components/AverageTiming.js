import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import React from 'react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar, } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import { getCubeDateRange, getTasksFilters } from '../utils'
import { useDeepCompareMemo } from 'use-deep-compare'
import {
  formatDayDimension, getBackgroundColor, TIMING_TOO_EARLY, TIMING_TOO_LATE,
  TYPE_DROPOFF,
  TYPE_PICKUP,
} from '../tasksGraphUtils'

const defaultMinMaxX = 6 * 60 // in minutes
const defaultMaxX = defaultMinMaxX

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'bottom',
    },
    tooltip: {
      callbacks: {
        label: function(context) {
          let label = context.dataset.label || '';

          if (label) {
            label += ': ';
          }
          if (context.parsed.y !== null) {
            label += `average ${Math.abs(Math.round(context.parsed.y))} minutes`;
          }
          return label;
        }
      },
    },
  },
  scales: {
    x: {
      ticks: {
        autoSkip: true,
      },
    },
  },
};

function typeFromSeries (s) {
  if (s.key.includes('PICKUP')) {
    return TYPE_PICKUP
  } else {
    return TYPE_DROPOFF
  }
}

function timingFromSeries (s) {
  if (s.key.includes('TooEarly')) {
    return TIMING_TOO_EARLY
  } else {
    return TIMING_TOO_LATE
  }
}

const pivotConfig = {
  "x": [
    "Task.intervalEndAt.day"
  ],
  "y": [
    "Task.type",
    "measures"
  ],
  "fillMissingDates": true,
  "joinDateRange": false
}

const BarChartRenderer = ({ resultSet }) => {
  const datasets = useDeepCompareMemo(
    () =>
      resultSet.series().map((s) => ({
        get label() {
          if (s.key.includes('PICKUP,Task.averageTooEarly')) {
            return "PICKUP early"
          } else if (s.key.includes('PICKUP,Task.averageTooLate')) {
            return "PICKUP late"
          } else if (s.key.includes('DROPOFF,Task.averageTooEarly')) {
            return "DROPOFF early"
          } else if (s.key.includes('DROPOFF,Task.averageTooLate')) {
            return "DROPOFF late"
          }else {
            return s.title
          }
        },
        data: s.series.map((r) => {
          let value = Math.abs(r.value)

          if (value > defaultMaxX) {
            value = defaultMaxX
          }

          if (s.key.includes('Task.averageTooLate')) {
            return -1 * value
          } else {
            return value
          }
        }),
        backgroundColor: s.series.map(() => {
          return getBackgroundColor(typeFromSeries(s), timingFromSeries(s))
        }),
        fill: false,
        get stack() {
          if (s.key.includes('PICKUP')) {
            return "PICKUP"
          } else {
            return "DROPOFF"
          }
        }
      })),
    [resultSet]
  );
  const data = {
    labels: resultSet.categories().map((c) => formatDayDimension(c.x)),
    datasets,
  };
  const stacked = !(pivotConfig.x || []).includes('measures');
  const options = {
    ...commonOptions,
    scales: {
      x: { ...commonOptions.scales.x, stacked },
      y: { ...commonOptions.scales.y, stacked },
    },
  };
  return <Bar type="bar" data={data} options={options} />;
};

const ChartRenderer = ({ dateRange, tags }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "measures": [
      "Task.averageTooEarly",
      "Task.averageTooLate",
    ],
    "timeDimensions": [
      {
        "dimension": "Task.intervalEndAt",
        "granularity": "week",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": {
      "Task.type": "desc"
    },
    "filters": getTasksFilters(tags),
    "dimensions": [
      "Task.type"
    ],
    "limit": 5000,
    "segments": []
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  return <BarChartRenderer resultSet={resultSet} />;
};

export default ChartRenderer
