import React, { useMemo } from 'react';

import { connectWithRedux } from '../../[id]/redux/incidentStore';
import { selectIncident, selectOrder } from '../../[id]/redux/incidentSlice';
import { useSelector } from 'react-redux';
import IncidentImages from '../../[id]/components/IncidentImages';
import IncidentTimeline from '../../[id]/components/IncidentTimeline';
import CommentBox from '../../[id]/components/CommentBox';
import { Incident, IncidentEvent } from '../../../../api/types';
import { useTranslation } from 'react-i18next';

export default connectWithRedux(function () {
  const incident = useSelector(selectIncident) as Incident;
  const { t } = useTranslation();

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
      type: 'local_event__suggestion',
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
        <p style={{ fontWeight: 'bold' }}>{t('INCIDENTS_DESCRIPTION')}</p>
        <p className="mx-2" data-testid="incident-description">
          {incident?.description}
        </p>
      </div>
      <hr />
      <div className="row" data-testid="incident-attachments">
        <p>
          {t('INCIDENTS_ATTACHMENTS')} <span className="caret"></span>
        </p>
        <IncidentImages />
      </div>
      <hr />
      <div data-testid="incident-timeline">
        <IncidentTimeline events={events} />
        <CommentBox />
      </div>
    </div>
  );
});
