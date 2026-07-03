import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import React from 'react';
import { Chart as ChartJS, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(LinearScale, BarElement, Tooltip, Legend)
import {getCubeDateRange} from "../utils";
import { useDeepCompareMemo } from 'use-deep-compare'
import {
  getBackgroundColor, TIMING_ON_TIME,
  TIMING_TOO_EARLY,
  TIMING_TOO_LATE,
} from '../tasksGraphUtils'

const defaultMinMaxX = 6 * 60 // in minutes
const defaultMinX = -1 * defaultMinMaxX
const defaultMaxX = defaultMinMaxX

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      display: false,
    },
  },
  scales: {
    x: {
      type: 'linear',
    },
  },
};

function timingFromX (x) {
  if (x < 0) {
    return TIMING_TOO_EARLY
  } else if (x === 0) {
    return TIMING_ON_TIME
  } else {
    return TIMING_TOO_LATE
  }
}

function reduce(arr, biFunc) {
  return arr.reduce(function (acc, r) {
    return biFunc(acc, Number(r.x))
  }, 0)
}

const BarChartRenderer = ({ resultSet, pivotConfig, taskType }) => {
  let minMaxX = defaultMinMaxX

  const datasets = useDeepCompareMemo(
    () =>
      resultSet.series().map((s) => {
        let min = reduce(s.series, Math.min)
        let max = reduce(s.series, Math.max)

        minMaxX = Math.min(defaultMinMaxX, Math.max(Math.abs(min), Math.abs(max)))

        return ({
          label: s.title,
          data: s.series.map((r) => {
            let x = Number(r.x)

            if (x < defaultMinX) {
              x = defaultMinX
            }

            if (x > defaultMaxX) {
              x = defaultMaxX
            }

            return {
              x,
              y: r.value
            }
          }),
          backgroundColor: s.series.map((r) => {
            let x = Number(r.x)
            return getBackgroundColor(taskType, timingFromX(x))
          }),
          fill: false,
        })
      }),
    [resultSet]
  );
  const data = {
    labels: resultSet.categories().map((c) => c.x),
    datasets,
  };
  const stacked = !(pivotConfig.x || []).includes('measures');
  const options = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      tooltip: {
        callbacks: {
          title: function () {
            // hide title
            return '';
          },
          label: function (context) {
            let data = context.raw

            let label = '';

            if (data.x < 0) {
              if (data.x == defaultMinX) {
                label += `>${Math.abs(Math.round(data.x))} minutes early`
              } else {
                label += `~${Math.abs(Math.round(data.x))} minutes early`
              }
            } else if (data.x == 0) {
              label += `on time`
            } else {
              if (data.x == defaultMaxX) {
                label += `>${Math.abs(Math.round(data.x))} minutes late`
              } else {
                label += `~${Math.abs(Math.round(data.x))} minutes late`
              }
            }

            return `${data.y} ${taskType}: ${label}`;
          }
        },
      },
    },
    scales: {
      x: {
        ...commonOptions.scales.x,
        stacked,
        min: -1 * minMaxX,
        max: minMaxX,
        ticks: {
          autoSkip: true,
          maxRotation: 0,
          padding: 12,
          minRotation: 0,
          callback: function(value) {
            if (value === defaultMinX) {
              return ">"
            } else if (value === defaultMaxX) {
              return "<"
            } else if (value == 0) {
              return 'on time';
            } else {
              return Math.abs(Math.round(value)) + ' min';
            }
          },
          color: function (context) {
            let value = context.tick.value

            if (value < defaultMinX) {
              return '#000000'
            } else if (value < 0) {
              return getBackgroundColor(taskType, TIMING_TOO_EARLY)
            } else if (value == 0) {
              return getBackgroundColor(taskType, TIMING_ON_TIME)
            } else if (value <= defaultMaxX) {
              return getBackgroundColor(taskType, TIMING_TOO_LATE)
            } else {
              return '#000000'
            }
          }
        },
      },
      y: { ...commonOptions.scales.y, stacked },
    },
  };
  return <Bar type="bar" data={data} options={options} />;
};

const pivotConfig = {
  "x": [
    "Task.notInIntervalMinutes"
  ],
  "y": [
    "measures"
  ],
  "fillMissingDates": true,
  "joinDateRange": false
};

const ChartRenderer = ({ dateRange, taskType }) => {
  const { resultSet, isLoading, error } = useCubeQuery({
    "dimensions": [
      "Task.notInIntervalMinutes"
    ],
    "timeDimensions": [
      {
        "dimension": "Task.intervalEndAt",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
    "order": [
      [
        "Task.notInIntervalMinutes",
        "asc"
      ]
    ],
    "measures": [
      "Task.countDone"
    ],
    "segments": [
      `Task.${taskType.toLowerCase()}`
    ],
    "filters": []
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
    return <Spin />;
  }

  return <BarChartRenderer resultSet={resultSet} pivotConfig={pivotConfig} taskType={taskType} />;
};

export default ChartRenderer
