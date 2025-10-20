import React, { useMemo } from 'react';

import { connectWithRedux } from './redux/incidentStore';
import { selectIncident, selectOrder } from './redux/incidentSlice';
import { useSelector } from 'react-redux';
import IncidentImages from './IncidentImages';
import IncidentTimeline from './IncidentTimeline';
import CommentBox from './CommentBox';
import { OrderDetailsSuggestion } from './OrderDetailsSuggestion';
import { IncidentMetadataSuggestion } from '../../../../api/types';

export default connectWithRedux(function () {
  const incident = useSelector(selectIncident);
  const existingOrder = useSelector(selectOrder);

  const suggestion = useMemo(() => {
    if (!incident?.metadata) {
      return null;
    }

    const suggestionObj = incident.metadata.find(el => Boolean(el.suggestion));

    if (!suggestionObj) {
      return null;
    }

    return suggestionObj.suggestion as IncidentMetadataSuggestion;
  }, [incident?.metadata]);

  return (
    <div>
      <div className="row">
        <p style={{ fontWeight: 'bold' }}>Description</p>
        <p className="mx-2">{incident?.description}</p>
      </div>
      {existingOrder && suggestion ? (
        <OrderDetailsSuggestion
          existingOrder={existingOrder}
          suggestion={suggestion}
        />
      ) : null}
      <hr />
      <div className="row">
        <p>
          Attachments <span className="caret"></span>
        </p>
        <IncidentImages />
      </div>
      <hr />
      <div>
        <IncidentTimeline />
        <CommentBox />
      </div>
    </div>
  );
});
