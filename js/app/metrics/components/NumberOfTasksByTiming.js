import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar, } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import { useDeepCompareMemo } from 'use-deep-compare'
import {
  formatDayDimension, getBackgroundColor, TIMING_TOO_EARLY, TIMING_TOO_LATE,
  TYPE_DROPOFF,
  TYPE_PICKUP,
} from '../tasksGraphUtils'

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'bottom',
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
  if (s.key.includes('countTooEarly')) {
    return TIMING_TOO_EARLY
  } else {
    return TIMING_TOO_LATE
  }
}

const BarChartRenderer = ({ resultSet, pivotConfig }) => {
  const datasets = useDeepCompareMemo(
    () =>
      resultSet.series().map((s) => ({
        label: s.title,
        data: s.series.map((r) => r.value),
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
    labels: resultSet.categories().map((c) => formatDayDimension(c.category)),
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
        "measures": [
          "Task.countTooEarly",
          "Task.countTooLate",
        ],
        "timeDimensions": [
          {
            "dimension": "Task.intervalEndAt",
            "granularity": "day",
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
            "Task.intervalEndAt.day"
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
