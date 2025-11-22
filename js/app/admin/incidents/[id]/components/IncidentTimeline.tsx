import React from 'react';

import { IncidentEvent } from '../../../../api/types';
import { IncidentEventView } from './IncidentEventView';

type Props = {
  events: IncidentEvent[];
};

export default function ({ events }: Props) {
  return (
    <div>
      {events.map(event => (
        <IncidentEventView key={event.id} event={event} />
      ))}
    </div>
  );
}
