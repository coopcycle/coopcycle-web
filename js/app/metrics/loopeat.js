import React from 'react'
import { createRoot } from 'react-dom/client'
import cubejs from '@cubejs-client/core';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin, Table } from 'antd';

import 'antd/lib/pagination/style/index.css'
import 'antd/lib/select/style/index.css'

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  const renderChart = ({ resultSet, error, pivotConfig }) => {
    if (error) {
      return <div>{error.toString()}</div>;
    }

    if (!resultSet) {
      return <Spin />;
    }

    return (
      <Table
        size="small"
        rowKey="Loopeat.orderNumber"
        pagination={{
          pageSize: 15
        }}
        columns={resultSet.tableColumns(pivotConfig)}
        dataSource={resultSet.tablePivot(pivotConfig)}
      />
    );
  };

  const ChartRenderer = () => {
    return (
      <QueryRenderer
        query={ JSON.parse(rootElement.dataset.query) }
        cubejsApi={cubejsApi}
        resetResultSetOnChange={false}
        render={(props) => renderChart({
          ...props,
          chartType: 'table',
          pivotConfig: {
            "x": [
              "Loopeat.restaurantName",
              "Loopeat.orderNumber",
              "Loopeat.orderDate",
              "Loopeat.customerEmail",
            ],
            "y": [],
            "fillMissingDates": true,
            "joinDateRange": false
          }
        })}
      />
    );
  };

  createRoot(rootElement).render(<ChartRenderer />);
}
