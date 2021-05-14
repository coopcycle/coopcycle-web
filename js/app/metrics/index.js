import React from 'react'
import ReactDOM from 'react-dom'
import cubejs from '@cubejs-client/core';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin, ConfigProvider, DatePicker, Select } from 'antd';
import { Bar } from 'react-chartjs-2';
import moment from 'moment'

import { antdLocale } from '../i18n'

import './index.scss'

const COLORS_SERIES = ['#FF6492', '#141446', '#7A77FF'];
const commonOptions = {
  maintainAspectRatio: false,
};

const rootElement = document.getElementById('cubejs');

if (rootElement) {

  const cubejsApi = cubejs(
    rootElement.dataset.token,
    { apiUrl: rootElement.dataset.apiUrl }
  );

  const renderChart = ({ resultSet, error }) => {
    if (error) {
      return <div>{error.toString()}</div>;
    }

    if (!resultSet) {
      return <Spin />;
    }

    const data = {
      labels: resultSet.categories().map((c) => moment(c.category).format('dddd D')),
      datasets: resultSet.series().map((s, index) => ({
        label: 'Orders', // s.title,
        data: s.series.map((r) => r.value),
        backgroundColor: COLORS_SERIES[index],
        fill: false,
      })),
    };
    const options = {
      ...commonOptions,
      scales: {
        xAxes: [
          {
            stacked: true,
          },
        ],
      },
    };
    return <Bar data={data} options={options} />;

  };

  const ChartRenderer = () => {
    return (
      <QueryRenderer
        query={{
          "measures": [
            "OrderPerVendor.count"
          ],
          "timeDimensions": [
            {
              "dimension": "OrderPerVendor.shippingTimeRange",
              "granularity": "day",
              "dateRange": JSON.parse(rootElement.dataset.dateRange)
            }
          ],
          "order": {},
          "filters": [
            {
              "member": "OrderPerVendor.state",
              "operator": "equals",
              "values": [
                "fulfilled"
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
              "OrderPerVendor.shippingTimeRange.day"
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

  ReactDOM.render(<ChartRenderer />, rootElement);
}

const CustomDatePicker = ({ defaultPickerType, defaultValue, routeName, restaurant }) => {

  const [ pickerType, setPickerType ] = React.useState(defaultPickerType)

  return (
    <ConfigProvider locale={ antdLocale }>
      <Select
        defaultValue={ pickerType }
        onChange={ (value) => setPickerType(value) }
        style={{ width: 120, marginRight: '5px' }}>
        <Select.Option value="month">Month</Select.Option>
        <Select.Option value="date">Date</Select.Option>
      </Select>
      <DatePicker
        value={ pickerType === defaultPickerType ? moment(defaultValue) : null }
        onChange={ (date, dateString) => {
          window.location.href = window.Routing.generate(routeName, {
            id: restaurant,
            [ pickerType ]: dateString
          })
        }}
        picker={ pickerType } />
    </ConfigProvider>
  )
}

const monthPickerEl = document.querySelector('#month-picker')

const routeName     = monthPickerEl.dataset.routeName
const restaurant    = monthPickerEl.dataset.restaurant
const defaultValue  = monthPickerEl.dataset.defaultValue
const pickerType    = monthPickerEl.dataset.pickerType

ReactDOM.render(
  <CustomDatePicker
    defaultPickerType={ pickerType }
    defaultValue={ defaultValue }
    routeName={ routeName }
    restaurant={ restaurant } />, monthPickerEl)

$('[data-toggle="tooltip"]').tooltip()
