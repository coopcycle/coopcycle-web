import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import {formatDayDimension, getBackgroundColor, getLabel} from "../tasksGraphUtils";

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'right',
    },
    tooltip: {
      filter: function(tooltipItem) {
        return tooltipItem.raw !== 0
      },
      callbacks: {
        label: function(context) {
          var label = context.dataset.label || '';

          if (label) {
            label += ': ';
          }
          if (context.parsed.y !== null) {
            label += `${context.parsed.y}%`;
          }
          return label;
        }
      }
    }
  },
  scales: {
    x: {
      ticks: {
        autoSkip: true,
      },
    },
  },
};

const BarChartRenderer = ({ resultSet, pivotConfig }) => {
  const data = {
    labels: resultSet.categories().map((c) => formatDayDimension(c.category)),
    datasets: resultSet.series().map((s) => (
        {
          label: getLabel(s.key),
          data: s.series.map((r) => r.value),
          backgroundColor: getBackgroundColor(s.key),
          fill: false,
        })),
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
          "Task.Percentage_of_TODO",
          "Task.Percentage_of_DOING",
          "Task.Percentage_of_FAILED",
          "Task.Percentage_of_CANCELLED",
          "Task.Percentage_of_DONE"
        ],
        "timeDimensions": [
          {
            "dimension": "Task.date",
            "granularity": "day",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": {
          "Task.date": "asc"
        },
        "filters": []
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Task.date.day"
          ],
          "y": [
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
