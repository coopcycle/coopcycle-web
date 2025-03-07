import React from 'react'
import { QueryRenderer } from '@cubejs-client/react';
import { Spin, Statistic } from 'antd';

import { getCurrencySymbol } from '../../i18n'
import { getCubeDateRange } from '../utils'

const renderChart = ({ resultSet, error }) => {
  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (!resultSet) {
    return <Spin />;
  }

  return (
    <React.Fragment>
    { resultSet.seriesNames().map((s) => (
      <Statistic
        key={ s.key }
        value={ resultSet.totalRow()[s.key] }
        precision={ 2 }
        suffix={ getCurrencySymbol() } />
    ))}
    </React.Fragment>
  )
};

const Chart = ({ cubejsApi, dateRange }) => {

  return (
    <QueryRenderer
      query={{
        "measures": [
          "OrderExport.total_incl_tax_avg"
        ],
        "timeDimensions": [
          {
            "dimension": "OrderExport.completed_at",
            "dateRange": getCubeDateRange(dateRange)
          }
        ],
      }}
      cubejsApi={cubejsApi}
      resetResultSetOnChange={false}
      render={(props) => renderChart({
        ...props,
        chartType: 'line',
        pivotConfig: {
          "x": [],
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
