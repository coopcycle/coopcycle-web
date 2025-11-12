import React from 'react';
import { Timeline } from 'antd';
import moment from 'moment';
import { OrderEvent } from '../../../api/types';

//TODO task events use .name instead of .type

const itemColor = (event: OrderEvent) => {
  switch (event.type) {
    case 'task:done':
      return 'green';
    case 'task:failed':
    case 'task:cancelled':
      return 'red';
    case 'task:rescheduled':
    case 'task:incident-reported':
      return 'orange';
    default:
      return 'blue';
  }
};

type Props = {
  events: OrderEvent[];
};

export function OrderHistory({ events }: Props) {
  events.sort((a, b) => {
    return moment(a.createdAt).isBefore(moment(b.createdAt)) ? -1 : 1;
  });

  const timelineItems = events.map(event => ({
    key: event.createdAt + '-' + event.type,
    color: itemColor(event),
    children: (
      <>
        <p>
          {moment(event.createdAt).format('lll')} {event.type}
        </p>
        {/*{event.data.incident_id && (*/}
        {/*  <a*/}
        {/*    href={window.Routing.generate('admin_incident', {*/}
        {/*      id: event.data.incident_id,*/}
        {/*    })}*/}
        {/*    target="_blank"*/}
        {/*    rel="noopener noreferrer">*/}
        {/*    Incident #{event.data.incident_id}*/}
        {/*  </a>*/}
        {/*)}*/}
        {/*{event.data.notes && (*/}
        {/*  <p>*/}
        {/*    <i className="fa fa-comment" aria-hidden="true"></i>{' '}*/}
        {/*    {event.data.notes}*/}
        {/*  </p>*/}
        {/*)}*/}
      </>
    ),
  }));

  return <Timeline items={timelineItems} />;
}
