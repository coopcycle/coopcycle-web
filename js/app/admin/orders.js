import { createRoot } from 'react-dom/client'
import cubejs from '@cubejs-client/core';
import { QueryRenderer } from '@cubejs-client/react';
import { Spin, Popover, Button } from 'antd';
import React, { useImperativeHandle, createRef, forwardRef, useState } from 'react';
import 'chart.js/auto'; // ideally we should only import the component that we need: https://react-chartjs-2.js.org/docs/migration-to-v4/#tree-shaking
import { Line } from 'react-chartjs-2';
import moment from 'moment'
import { ThreeDots } from 'react-loader-spinner'
import { parsePhoneNumberFromString } from 'libphonenumber-js';
import { phoneNumber as formatPhoneNumber } from '../utils/format';

import dayjs from 'dayjs'
import 'dayjs/locale/en';
import 'dayjs/locale/es';
import 'dayjs/locale/fr';

import { useTranslation } from 'react-i18next';

import relativeTime from 'dayjs/plugin/relativeTime';

dayjs.extend(relativeTime);

const locale = $('html').attr('lang')

dayjs.locale(locale);

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
      labels: resultSet.categories().map((c) => moment(c.x).format('L')),
      datasets: resultSet.series().map((s, index) => ({
        label: s.title,
        data: s.series.map((r) => r.value),
        borderColor: COLORS_SERIES[index],
        fill: false,
      })),
    };
    const options = { ...commonOptions };
    return <Line data={data} options={options} />;

  };

  const ChartRenderer = () => {
    return (
      <QueryRenderer
        query={{
          "measures": [
            "PlatformFee.totalAmount"
          ],
          "timeDimensions": [
            {
              "dimension": "Order.shippingTimeRange",
              "granularity": "day",
              "dateRange": "last 90 days"
            }
          ],
          "order": {},
          "dimensions": [],
          "filters": [
            {
              "member": "Order.state",
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
          chartType: 'line',
          pivotConfig: {
            "x": [
              "Order.shippingTimeRange.day"
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

  createRoot(rootElement).render(<ChartRenderer />);
}

const httpClient = new window._auth.httpClient();

const CustomerPopoverContent = ({ isLoading, customer, customerInsights }) => {

  const { t } = useTranslation();

  if (isLoading) {
    return (
      <ThreeDots wrapperClass="justify-content-center" width="24" height="24" />
    )
  }

  return (
    <div>
      <ul className="list-unstyled">
        <li><a href={`mailto:${customer.email}`}>{customer.email}</a></li>
        <li>{formatPhoneNumber(customer.phoneNumber)}</li>
      </ul>
      <hr/>
      <ul className="list-unstyled">
        <li>{ t('CUSTOMER_INSIGHTS.NUMBER_OF_ORDERS', { count: customerInsights.numberOfOrders }) }</li>
        { customerInsights.firstOrderedAt ? (
        <li>{ t('CUSTOMER_INSIGHTS.FIRST_ORDER', { date: dayjs().to(dayjs(customerInsights.firstOrderedAt)) }) }</li>) : null }
        { customerInsights.lastOrderedAt ? (
        <li>{ t('CUSTOMER_INSIGHTS.LAST_ORDER', { date: dayjs().to(dayjs(customerInsights.lastOrderedAt)) }) }</li>) : null }
        { customerInsights.favoriteRestaurant ? (
        <li>{ t('CUSTOMER_INSIGHTS.FAVORITE_RESTAURANT', { name: customerInsights.favoriteRestaurant.name }) }</li>) : null }
      </ul>
    </div>
  )
}

const CustomerPopover = forwardRef(({ iri }, ref) => {

  const [isOpen, setIsOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [customer, setCustomer] = useState(null)
  const [customerInsights, setCustomerInsights] = useState(null)

  useImperativeHandle(ref, () => ({
    async open() {

      setIsLoading(true)
      setIsOpen(true)

      const { response: customer } = await httpClient.get(iri);
      const { response: insights } = await httpClient.get(`${iri}/insights`);

      let favoriteRestaurant = null
      if (insights.favoriteRestaurant) {
        const { response: fav } = await httpClient.get(insights.favoriteRestaurant)
        favoriteRestaurant = fav
      }
      setCustomer(customer)
      setCustomerInsights({
        ...insights,
        favoriteRestaurant
      })
      setIsLoading(false)

    },
  }), []);

  return (
    <Popover
      content={
        <CustomerPopoverContent
          isLoading={isLoading}
          customer={customer}
          customerInsights={customerInsights} />
      }
      trigger="click"
      open={isOpen}
      onOpenChange={setIsOpen}
      placement="right">
    </Popover>
  )
})

document.querySelectorAll('[data-customer-insights]').forEach(customerEl => {

  const container = document.createElement("span");

  customerEl.appendChild(container)

  const ref = createRef()

  createRoot(container).render(<CustomerPopover ref={ref} iri={ customerEl.dataset.customer } />)

  customerEl.addEventListener('click', (e) => {
    e.preventDefault();
    ref.current.open();
  })
})
