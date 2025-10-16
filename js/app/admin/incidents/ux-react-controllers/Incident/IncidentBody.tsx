import React from 'react';

import { connectWithRedux, selectIncident } from './incidentStore';
import { useSelector } from 'react-redux';
import IncidentImages from './IncidentImages';
import IncidentTimeline from './IncidentTimeline';
import CommentBox from './CommentBox';

export default connectWithRedux(function () {
  const incident = useSelector(selectIncident);

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
        <IncidentTimeline />
        <CommentBox />
      </div>
    </div>
  );
});
