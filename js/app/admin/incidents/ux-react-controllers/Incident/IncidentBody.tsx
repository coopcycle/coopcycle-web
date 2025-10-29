import React, { useMemo } from 'react';

import { connectWithRedux } from './redux/incidentStore';
import { selectIncident, selectOrder } from './redux/incidentSlice';
import { useSelector } from 'react-redux';
import IncidentImages from './IncidentImages';
import IncidentTimeline from './IncidentTimeline';
import CommentBox from './CommentBox';
import { Incident, IncidentEvent } from '../../../../api/types';

export default connectWithRedux(function () {
  const incident = useSelector(selectIncident) as Incident;

  const existingOrder = useSelector(selectOrder);

  const events = useMemo(() => {
    if (!incident.metadata) {
      return incident.events;
    }

    if (!existingOrder) {
      return incident.events;
    }

    const suggestionObj = incident.metadata.find(el => Boolean(el.suggestion));

    if (!suggestionObj) {
      return incident.events;
    }

    const isHandled = incident.events.some(
      event =>
        event.type === 'accepted_suggestion' ||
        event.type === 'rejected_suggestion',
    );

    // handled suggestion will be rendered in the timeline
    if (isHandled) {
      return incident.events;
    }

    const suggestionEvent: IncidentEvent = {
      id: 0,
      type: 'local_type_suggestion',
      message: '',
      metadata: [
        {
          suggestion: suggestionObj.suggestion,
        },
      ],
      createdBy: incident.createdBy,
      createdAt: incident.createdAt,
    };

    return [suggestionEvent, ...incident.events];
  }, [incident, existingOrder]);

  return (
    <div>
      <div className="row">
        <p style={{ fontWeight: 'bold' }}>Description</p>
        <p className="mx-2">{incident?.description}</p>
      </div>
      <hr />
      <div className="row">
        <p>
          Attachments <span className="caret"></span>
        </p>
        <IncidentImages />
      </div>
      <hr />
      <div>
        <IncidentTimeline events={events} />
        <CommentBox />
      </div>
    </div>
  );
});
