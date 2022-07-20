import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin } from 'antd';
import moment from 'moment'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

import { getCubeDateRange } from '../utils'
import LineChart from '../../widgets/LineChart'

const renderChart = ({ resultSet, error }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  const data = {
    labels: resultSet.categories().map((c) => moment(c.category).format('MMMM')),
    datasets: resultSet.series().map((s, index) => ({
      label: 'Number of stores',
      data: s.series.map((r) => r.value),
      borderColor: COLORS_SERIES[index],
      fill: false,
    })),
  };
  const options = {
    ...commonOptions,
    plugins: {
      legend: {
        display: false
      },
    },
    scales: {
      x: {
        stacked: true,
      },
    },
  };
  // return <BarChart data={data} options={options} />

  return <LineChart data={data} options={options} />

};

const Chart = ({ cubejsApi, dateRange }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "Store.cumulativeCount"
        ],
        "timeDimensions": [
          {
            "dimension": "Store.createdAt",
            "granularity": "month",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
        "order": {
          "Store.createdAt": "asc"
        },
        "filters": [],
        "dimensions": []
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'bar',
        pivotConfig: {
          "x": [
            "Store.createdAt.month"
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

export default Chart
