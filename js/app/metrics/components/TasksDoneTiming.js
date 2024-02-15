import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import React from 'react';
import 'chart.js/auto'; // ideally we should only import the component that we need: https://react-chartjs-2.js.org/docs/migration-to-v4/#tree-shaking
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
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

const BarChartRenderer = ({ resultSet, pivotConfig }) => {
  let visibleSeries = [
    "Task.percentageOnTime",
    "Task.percentageTooEarly",
    "Task.percentageTooLate",
  ]

  function isVisible(series) {
    return _.findIndex(visibleSeries, function(el) { return series.key.includes(el) }) != -1
  }

  const datasets = useDeepCompareMemo(
    () =>
      resultSet.series().filter(s => isVisible(s)).map((s) => ({
        get label() {
          return `${timingToString(timingFromSeries(s))} ${typeToString(typeFromSeries(s))}`
        },
        data: s.series.map((r) => {
          if (s.key.includes('Task.percentageTooLate')) {
            return -1 * r.value
          } else {
            return r.value
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

  const extraDatasets = useDeepCompareMemo(
    () => resultSet.series().filter(s => !isVisible(s)),
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

            if (context.parsed.y !== null) {
              let extraValue = extraDatasets[context.datasetIndex].series[context.dataIndex]
              return `${extraValue.value} ${label} (${Math.round(Math.abs(context.parsed.y))}%)`
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

const renderChart = ({ resultSet, error, pivotConfig }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  return <BarChartRenderer resultSet={resultSet} pivotConfig={pivotConfig} />;

};

const ChartRenderer = ({ cubejsApi, dateRange }) => {
  return (
  <QueryRenderer
    query={{
      /**
       * order OnTime/TooEarly/TooLate must be the same for percentage and count
       * for tooltip to pick data correctly
       */
      "measures": [
        "Task.percentageTooLate",
        "Task.percentageOnTime",
        "Task.percentageTooEarly",
        "Task.countTooLate",
        "Task.countOnTime",
        "Task.countTooEarly",
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
      "filters": [],
      "dimensions": [
        "Task.type"
      ],
      "limit": 5000,
      "segments": []
    }}
    cubejsApi={cubejsApi}
    resetResultSetOnChange={false}
    render={(props) => renderChart({
      ...props,
      chartType: 'bar',
      pivotConfig: {
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
    })}
  />
  );
};

export default ChartRenderer
