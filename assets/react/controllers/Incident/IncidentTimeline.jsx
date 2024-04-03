import React from "react";
import moment from "moment";
import classNames from "classnames";
import "./IncidentTimeline.scss";

function Comment({ event }) {
  const { username } = event.createdBy;
  return (
    <div className="media-body">
      <div className="panel panel-default">
        <div className="panel-heading">
          <span className="font-weight-bold pr-1">{username}</span>
          <span className="text-muted font-weight-light">
            {moment(event.createdAt).fromNow()}
          </span>
        </div>
        <div className="panel-body">{event.message}</div>
      </div>
    </div>
  );
}

function _eventTypeToText(event) {
  switch (event.type) {
    case "rescheduled":
      return "rescheduled the task";
  }
}

function _metadataToText({ type, metadata }) {
  switch (type) {
    case "rescheduled":
      return moment(metadata.rescheduled).format("lll");
  }
}

function Event({ event }) {
  const { username } = event.createdBy;

  const metadata = _metadataToText(event);

  return (
    <div className="media-body">
      <span className="font-weight-bold">{username}</span>
      <span className="font-weight-light px-1">{_eventTypeToText(event)}</span>
      <span className="text-muted font-weight-light">
        {moment(event.createdAt).fromNow()}
      </span>
      <div className="text-monospace font-weight-light text-muted pl-1">
        {metadata}
      </div>
    </div>
  );
}

function _body(event) {
  switch (event.type) {
    case "comment":
      return <Comment event={event} />;
    default:
      return <Event event={event} />;
  }
}

function Item({ event }) {
  const { username } = event.createdBy;
  const eventType = event.type !== "comment";
  return (
    <div className={classNames("media", { event: eventType })}>
      <div className="media-left media-top">
        <a href="#">
          <img
            className="media-object"
            width="32"
            src={window.Routing.generate("user_avatar", { username })}
            alt={username}
          />
        </a>
      </div>
      {_body(event)}
    </div>
  );
}

export default function ({ events }) {
  events = JSON.parse(events);
  console.log(events);

  return (
    <div className="tl-incident-event">
      {events.map((event) => (
        <Item key={event.id} event={event} />
      ))}
    </div>
  );
}
