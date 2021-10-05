import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import 'antd/dist/antd.css';
import React from 'react';
import { Bar, } from 'react-chartjs-2';
import {getCubeDateRange} from "../utils";
import {formatDayDimension, getBackgroundColor, getLabel} from "../tasksGraphUtils";
import ChartDataLabels from 'chartjs-plugin-datalabels';

const commonOptions = {
  maintainAspectRatio: false,
  interaction: {
    intersect: false,
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      filter: function(tooltipItem) {
        if (tooltipItem.dataset.label === 'Task.count') return false
        if (tooltipItem.raw === 0) return false

        return true
      },
    },
    datalabels: {
      align: 'end',
      anchor: 'end',
      // color: function(context) {
      //   return context.dataset.backgroundColor;
      // },
      // font: function(context) {
      //   var w = context.chart.width;
      //   return {
      //     size: w < 512 ? 12 : 14,
      //     weight: 'bold',
      //   };
      // },
      formatter: function(value, context) {
        if (context.dataset.label !== 'Task.count') return ''
        if (value === 0) return ''
        return value
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
    datasets: resultSet.series().map((s) => {
      if (s.key === 'Task.count') {
        return ({
          label: s.key,
          data: s.series.map((r) => r.value),
          backgroundColor: '#ffffff00',
          borderColor: '#ffffff00',
          fill: false,
          type: 'line',
        })
      } else {
        return ({
          label: getLabel(s.key),
          data: s.series.map((r) => r.value),
          backgroundColor: getBackgroundColor(s.key),
          fill: false,
        })
      }
    }),
  };
  const stacked = !(pivotConfig.x || []).includes('measures');
  const options = {
    ...commonOptions,
    scales: {
      x: { ...commonOptions.scales.x, stacked },
      y: { ...commonOptions.scales.y, stacked },
    },
  };
  return <Bar plugins={[ChartDataLabels]} type="bar" data={data} options={options} />;
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
          "Task.Total_DONE_tasks",
          "Task.Total_TODO_tasks",
          "Task.Total_DOING_tasks",
          "Task.Total_FAILED_tasks",
          "Task.Total_CANCELLED_tasks",
          "Task.count",
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
