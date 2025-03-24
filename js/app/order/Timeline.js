import React from 'react';
import moment from 'moment';
import _ from 'lodash';

import i18n from '../i18n';
import TimelineStep from './TimelineStep';
import ShippingTimeRange from '../components/ShippingTimeRange';
import { ApiProvider } from '@reduxjs/toolkit/dist/query/react';
import { createApi, fetchBaseQuery } from '@reduxjs/toolkit/query/react';

moment.locale($('html').attr('lang'));

const api = createApi({
  baseQuery: fetchBaseQuery({
    jsonContentType: 'application/ld+json'
  }),
  endpoints: (build) => ({
    getOrderState: build.query({
      query: args => ({url:  args.order['@id'], headers: { 'Authorization': `Bearer ${args.orderAccessToken}` }}),
    }),
  }),
})

const dateComparator = (a, b) => moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1;

const allowedEvents = [
  'order:created',
  'order:accepted',
  'order:refused',
  'order:cancelled',
  'order:picked',
  'order:dropped'
];

const OrderTimeline = ({ order, orderAccessToken, events: initialEvents }) => {

  const { data, isFetching, refetch } = api.useGetOrderStateQuery({order, orderAccessToken}, { pollingInterval: 6000, skipPollingIfUnfocused: true})
  const events = data ? data.events.filter(event => allowedEvents.includes(event.type)).map(event => ({ name: event.type, createdAt: event.createdAt })).sort(dateComparator) : initialEvents.sort(dateComparator)

  const renderEvent = (event, key) => {
    const date = moment(event.createdAt).format('LT');

    const eventMapping = {
      'order:created': { success: true, title: i18n.t('ORDER_TIMELINE_CREATED_TITLE', { date }) },
      'order:accepted': { success: true, title: i18n.t('ORDER_TIMELINE_ACCEPTED_TITLE', { date }), description: 'Description' },
      'order:refused': { danger: true, title: i18n.t('ORDER_TIMELINE_REFUSED_TITLE', { date }), description: 'Description' },
      'order:cancelled': { danger: true, title: i18n.t('ORDER_TIMELINE_CANCELLED_TITLE', { date }) },
      'order:picked': { success: true, title: i18n.t('ORDER_TIMELINE_PICKED_TITLE', { date }), description: 'Description' },
      'order:dropped': { success: true, title: i18n.t('ORDER_TIMELINE_DROPPED_TITLE', { date }), description: 'Description' },
    };

    return <TimelineStep key={key} {...eventMapping[event.name]} />;
  };

  const renderNextEvent = () => {
    const last = _.last(events);
    
    const nextEventMapping = {
      'order:created': { active: true, spinner: true, title: i18n.t('ORDER_TIMELINE_AFTER_CREATED_TITLE'), description: i18n.t('ORDER_TIMELINE_AFTER_CREATED_DESCRIPTION') },
      'order:accepted': { active: true, title: i18n.t('ORDER_TIMELINE_AFTER_ACCEPTED_TITLE'), description: i18n.t('ORDER_TIMELINE_AFTER_ACCEPTED_DESCRIPTION') },
      'order:picked': { active: true, title: i18n.t('ORDER_TIMELINE_AFTER_PICKED_TITLE'), description: i18n.t('ORDER_TIMELINE_AFTER_PICKED_DESCRIPTION') },
    };

    return last && nextEventMapping[last.name] ? <TimelineStep {...nextEventMapping[last.name]} /> : null;
  };

  return (
    <div className="border mb-3">
      <h4 className="bg-light p-3 m-0 clearfix">
        <ShippingTimeRange value={order.shippingTimeRange} />
        <button
          onClick={refetch}
          className="btn btn-default btn-sm pull-right"
          disabled={isFetching}
          title="Refresh events">
          <i className={`fa fa-refresh ${isFetching ? 'fa-spin' : ''}`}></i>
        </button>
      </h4>
      <div className="px-3 py-4">
        <div className="order-timeline">
          {events.map((event, key) => renderEvent(event, key))}
          {renderNextEvent()}
        </div>
      </div>
    </div>
  );
};

export default (props) => {
  return (
    <ApiProvider api={api}>
      <OrderTimeline {...props} />
    </ApiProvider>
  )
};
