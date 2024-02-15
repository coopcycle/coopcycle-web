import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import React from 'react';
import 'chart.js/auto'; // ideally we should only import the component that we need: https://react-chartjs-2.js.org/docs/migration-to-v4/#tree-shaking
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import { useDeepCompareMemo } from 'use-deep-compare'
import {
  getBackgroundColor, TIMING_ON_TIME,
  TIMING_TOO_EARLY,
  TIMING_TOO_LATE,
} from '../tasksGraphUtils'

const defaultMinMaxX = 450 // see cube/Task.js; == 400%
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
  if (x < -50) {
    return TIMING_TOO_EARLY
  } else if (x > 50) {
    return TIMING_TOO_LATE
  } else {
    return TIMING_ON_TIME
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

            if (data.x < -50) {
              if (data.x == defaultMinX) {
                label += `>${Math.abs(Math.round(data.x + 50))} % early`
              } else {
                label += `~${Math.abs(Math.round(data.x + 50))} % early`
              }
            } else if (data.x <= 50) {
              label += `on time (${Math.round(data.x + 50)})`
            } else {
              if (data.x == defaultMaxX) {
                label += `>${Math.abs(Math.round(data.x - 50))} % late`
              } else {
                label += `~${Math.abs(Math.round(data.x - 50))} % late`
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
            if (value <= defaultMinX) {
              return ">"
            } else if (value <= -50) {
              return Math.abs(value + 50) + ' %';
            } else if (value == 0) {
              return 'on time';
            } else if (value < 50) {
              return (value + 50) + ' %';
            } else if (value < defaultMaxX) {
              return (value - 50) + ' %';
            } else {
              return "<"
            }
          },
          color: function (context) {
            let value = context.tick.value

            if (value < defaultMinX) {
              return '#000000'
            } else if (value <= -50) {
              return getBackgroundColor(taskType, TIMING_TOO_EARLY)
            } else if (value < 50) {
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
          "Task.intervalDiff"
        ],
        "timeDimensions": [
          {
            "dimension": "Task.intervalEndAt",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": [
          [
            "Task.intervalDiff",
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
            "member": "Task.status",
            "operator": "contains",
            "values": [
              "DONE"
            ]
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
            "Task.intervalDiff"
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
