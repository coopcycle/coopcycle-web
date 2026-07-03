import React from 'react'
import { useCubeQuery } from '@cubejs-client/react';
import { Spin, Statistic } from 'antd';

import { getCurrencySymbol } from '../../i18n'
import { getCubeDateRange } from '../utils'

const Chart = ({ dateRange }) => {

  const { resultSet, isLoading, error } = useCubeQuery({
    "measures": [
      "OrderExport.total_incl_tax_avg"
    ],
    "timeDimensions": [
      {
        "dimension": "OrderExport.completed_at",
        "dateRange": getCubeDateRange(dateRange)
      }
    ],
  });

  if (error) {
    return <div>{error.toString()}</div>;
  }

  if (isLoading || !resultSet) {
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

export default Chart
