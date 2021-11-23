import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import { useDeepCompareMemo } from 'use-deep-compare'
import {
  getBackgroundColor, TIMING_ON_TIME,
  TIMING_TOO_EARLY,
  TIMING_TOO_LATE,
} from '../tasksGraphUtils'

const defaultMinMaxX = 12 * 60 // in minutes
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
      ticks: {
        autoSkip: true,
        maxRotation: 0,
        padding: 12,
        minRotation: 0,
        callback: function(value) {
          if (value === defaultMinX) {
            return "<"
          } else if (value === defaultMaxX) {
            return "<"
          } else {
            return Math.round(value) + ' min';
          }
        }
      },
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
              label += `${Math.abs(Math.round(data.x))} minutes early: `
            } else if (data.x == 0) {
              label += `on time: `
            } else {
              label += `${Math.abs(Math.round(data.x))} minutes late: `
            }

            // if (label) {
            //   label += ': ';
            // }
            // if (context.parsed.y !== null) {
            //   label += `average ${Math.abs(Math.round(context.parsed.y))} minutes`;
            // }
            return label += `${data.y} ${taskType}`;
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
      },
      y: { ...commonOptions.scales.y, stacked },
    },
  };
  return <Bar type="bar" data={data} options={options} />;
};

const renderChart = ({ resultSet, error, pivotConfig, taskType }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  return <BarChartRenderer resultSet={resultSet} pivotConfig={pivotConfig} taskType={taskType} />;

};

const ChartRenderer = ({ cubejsApi, dateRange, taskType }) => {
  return (
    <QueryRenderer
      query={{
        "dimensions": [
          "Task.notInIntervalMinutes"
        ],
        "timeDimensions": [],
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
        "filters": [
          {
            "member": "Task.intervalEndAt",
            "operator": "inDateRange",
            "values": getCubeDateRange(dateRange)
          }
        ]
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Task.notInIntervalMinutes"
          ],
          "y": [
            "measures"
          ],
          "fillMissingDates": true,
          "joinDateRange": false
        },
        taskType
      })}
    />
  );
};

export default ChartRenderer
