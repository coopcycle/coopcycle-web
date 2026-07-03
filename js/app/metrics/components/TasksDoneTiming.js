import { useCubeQuery } from '@cubejs-client/react';
import { Spin } from 'antd';
import React from 'react';
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip, Legend } from 'chart.js'
import { Bar } from 'react-chartjs-2';
ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend)
import { getCubeDateRange, getTasksFilters } from '../utils'
import { useDeepCompareMemo } from 'use-deep-compare';
import {
  formatDayDimension,
  getBackgroundColor,
  TIMING_ON_TIME,
  TIMING_TOO_EARLY,
  TIMING_TOO_LATE,
  TYPE_DROPOFF,
  TYPE_PICKUP,
} from '../tasksGraphUtils'
import _ from 'lodash'

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'right',
      labels: {
        sort: function(a, b) {
          // a: LegendItem, b: LegendItem, data: ChartData

          let order = [
            {type: TYPE_PICKUP, timing: TIMING_TOO_EARLY},
            {type: TYPE_DROPOFF, timing: TIMING_TOO_EARLY},
            {type: TYPE_PICKUP, timing: TIMING_ON_TIME},
            {type: TYPE_DROPOFF, timing: TIMING_ON_TIME},
            {type: TYPE_PICKUP, timing: TIMING_TOO_LATE},
            {type: TYPE_DROPOFF, timing: TIMING_TOO_LATE},
          ]

          function indexOf(item) {
            return _.findIndex(order, function(o) {
              return item.text.includes(typeToString(o.type)) && item.text.includes(timingToString(o.timing))
            });
          }

          let aIndex = indexOf(a)
          let bIndex = indexOf(b)

          return aIndex - bIndex
        }
      }
    },
    tooltip: {
      filter: function(el) {
        // el: TooltipItem
        return el.raw != 0 //hide items with value == 0
      }
    }
  },
  scales: {
    x: {
      ticks: {
        autoSkip: true,
      },
    },
    y: {
      max: 100,
      min: -100,
      ticks: {
        stepSize: 50,
        callback: function(value) {
          return Math.abs(value) + '%';
        }
      },
    }
  },
};

function typeFromSeries (s) {
  if (s.key.includes('PICKUP')) {
    return TYPE_PICKUP
  } else {
    return TYPE_DROPOFF
  }
}

function typeToString(type) {
  if (type == TYPE_PICKUP) {
    return 'PICKUP'
  } else if (type == TYPE_DROPOFF) {
    return 'DROPOFF'
  } else {
    return 'unknown type'
  }
}

function timingFromSeries (s) {
  if (s.key.includes('TooEarly')) {
    return TIMING_TOO_EARLY
  } else if (s.key.includes('TooLate')) {
    return TIMING_TOO_LATE
  }else {
    return TIMING_ON_TIME
  }
}

function timingToString(timing) {
  if (timing == TIMING_TOO_EARLY) {
    return 'early'
  } else if (timing == TIMING_TOO_LATE) {
    return 'late'
  } else if (timing == TIMING_ON_TIME) {
    return 'on time'
  }  else {
    return 'unknown timing'
  }
}

const pivotConfig = {
  "x": [
    "Task.intervalEndAt.day",
  ],
  "y": [
    "Task.type",
    "measures"
  ],
  "fillMissingDates": true,
  "joinDateRange": false
}

const BarChartRenderer = ({ resultSet }) => {
  let visibleSeries = [
    "Task.countTooLate",
    "Task.countOnTime",
    "Task.countTooEarly",
  ]

  function isVisible(series) {
    return _.findIndex(visibleSeries, function(el) { return series.key.includes(el) }) != -1
  }

  let series = resultSet.series()

  let pickupDoneSeries = series.find(el => el.key == "PICKUP,Task.countDone")
  let dropoffDoneSeries = series.find(el => el.key == "DROPOFF,Task.countDone")

  const datasets = useDeepCompareMemo(
    () =>
      series.filter(s => isVisible(s)).map((s) => ({
        get label() {
          return `${timingToString(timingFromSeries(s))} ${typeToString(typeFromSeries(s))}`
        },
        data: s.series.map((r, index) => {
          let doneSeries
          if (s.key.includes('PICKUP')) {
            doneSeries = pickupDoneSeries
          } else {
            doneSeries = dropoffDoneSeries
          }

          let percentage = Math.round(r.value / doneSeries.series[index].value * 100)

          if (s.key.includes('Task.countTooLate')) {
            return -1 * percentage
          } else {
            return percentage
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
        },
      })),
    [resultSet]
  );

  const countDatasets = useDeepCompareMemo(
    () => resultSet.series().filter(s => isVisible(s)),
    [resultSet])

  const data = {
    labels: resultSet.categories().map((c) => formatDayDimension(c.x)),
    datasets,
  };
  const stacked = !(pivotConfig.x || []).includes('measures');

  const options = {
    ...commonOptions,
    plugins: {
      ...commonOptions.plugins,
      tooltip: {
        ...commonOptions.plugins.tooltip,
        callbacks: {
          label: function(context) {
            let label = context.dataset.label || '';

            let percentageValue = context.parsed.y

            if (percentageValue !== null) {
              let countValue = countDatasets[context.datasetIndex].series[context.dataIndex]
              return `${countValue.value} ${label} (${Math.abs(percentageValue)}%)`
            }
            return label;
          }
        },
      },
    },
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
      "Task.countTooLate",
      "Task.countOnTime",
      "Task.countTooEarly",
      "Task.countDone",
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
