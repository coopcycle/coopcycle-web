import React from 'react';
import moment from 'moment';
import classNames from 'classnames';
import { money } from './utils';

import { useTranslation } from 'react-i18next';
import { IncidentEvent } from '../../../../api/types';
import { OrderDetailsSuggestion } from './OrderDetailsSuggestion';
import { useUsername } from './useUsername';

type Props = {
  events: IncidentEvent[];
};

function _eventTypeToText(event: IncidentEvent) {
  switch (event.type) {
    case 'rescheduled':
      return 'RESCHEDULED_THE_TASK';
    case 'cancelled':
      return 'CANCELLED_THE_TASK';
    case 'applied_price_diff':
      return 'APPLIED_A_DIFFERENCE_ON_THE_PRICE';
    case 'transporter_reported':
      return 'SENT_A_REPORT_TO_THE_TRANSPORTER';
    case 'accepted_suggestion':
      return 'ACCEPTED_SUGGESTION';
    case 'rejected_suggestion':
      return 'REJECTED_SUGGESTION';
  }
}

function _metadataToText({ type, metadata }) {
  switch (type) {
    case 'rescheduled':
      return (
        <>
          <div>
            <span style={{ width: '55px', display: 'inline-block' }}>
              From:
            </span>
            {moment(metadata.from.after).format('l LT')} to{' '}
            {moment(metadata.from.before).format('l LT')}
          </div>
          <div>
            <span style={{ width: '55px', display: 'inline-block' }}>To:</span>
            {moment(metadata.to.after).format('l LT')} to{' '}
            {moment(metadata.to.before).format('l LT')}
          </div>
        </>
      );
    case 'applied_price_diff':
      return money(metadata.diff);
  }
}

function MediaPost({
  event,
  children,
}: {
  event: IncidentEvent;
  children: React.ReactNode;
}) {
  const username = useUsername(event.createdBy);

  return (
    <div className="media-body">
      <div className="panel panel-default">
        <div className="panel-heading">
          <span className="font-weight-bold pr-1">{username}</span>
          <span className="text-muted font-weight-light">
            {moment(event.createdAt).fromNow()}
          </span>
        </div>
        <div className="panel-body" style={{ whiteSpace: 'pre-line' }}>
          {children}
        </div>
      </div>
    </div>
  );
}

function Event({ event }: { event: IncidentEvent }) {
  const username = useUsername(event.createdBy);

  const { t } = useTranslation();

  const metadata = _metadataToText(event);

  return (
    <div
      className="media-body"
      style={{
        paddingBottom: '20px',
        paddingLeft: '10px',
        lineHeight: '32px',
      }}>
      <span className="font-weight-bold">{username}</span>
      <span className="font-weight-light px-1">
        {t(_eventTypeToText(event))}
      </span>
      <span className="text-muted font-weight-light">
        {moment(event.createdAt).fromNow()}
      </span>
      <div className="text-monospace font-weight-light text-muted pl-1">
        {metadata}
      </div>
    </div>
  );
}

function Body({ event }: { event: IncidentEvent }) {
  switch (event.type) {
    case 'commented':
      return <MediaPost event={event}>{event.message}</MediaPost>;
    case 'local_type_suggestion': {
      return (
        <MediaPost event={event}>
          <OrderDetailsSuggestion event={event} />
        </MediaPost>
      );
    }
    default:
      return <Event event={event} />;
  }
}

function Avatar({ event }: { event: IncidentEvent }) {
  const username = useUsername(event.createdBy);

  return (
    <img
      className="media-object"
      width="32"
      src={window.Routing.generate('user_avatar', {
        username: username ?? 'unknown',
      })}
      alt={username}
    />
  );
}

function Item({ event }: { event: IncidentEvent }) {
  const eventType = event.type !== 'commented';
  return (
    <div className={classNames('media', { event: eventType })}>
      <div className="media-left media-top">
        <Avatar event={event} />
      </div>
      <Body event={event} />
    </div>
  );
}

export default function ({ events }: Props) {
  return (
    <div>
      {events.map(event => (
        <Item key={event.id} event={event} />
      ))}
    </div>
  );
}
