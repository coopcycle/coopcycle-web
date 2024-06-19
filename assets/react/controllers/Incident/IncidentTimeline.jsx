import React from "react";
import moment from "moment";
import classNames from "classnames";
import "./IncidentTimeline.scss";
import { money } from "./utils";

import { connectWithRedux } from "./incidentStore";
import { useSelector } from "react-redux";
import { useTranslation } from "react-i18next";

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
        <div className="panel-body" style={{ whiteSpace: "pre-line" }}>
          {event.message}
        </div>
      </div>
    </div>
  );
}

function _eventTypeToText(event) {
  switch (event.type) {
    case "rescheduled":
      return "RESCHEDULED_THE_TASK";
    case "cancelled":
      return "CANCELLED_THE_TASK";
    case "applied_price_diff":
      return "APPLIED_A_DIFFERENCE_ON_THE_PRICE";
    case "transporter_reported":
      return "SENT_A_REPORT_TO_THE_TRANSPORTER";
  }
}

function _metadataToText({ type, metadata }) {
  switch (type) {
    case "rescheduled":
      return (
        <>
          <div>
            <span style={{ width: "55px", display: "inline-block" }}>
              From:
            </span>
            {moment(metadata.from.after).format("l LT")} to{" "}
            {moment(metadata.from.before).format("l LT")}
          </div>
          <div>
            <span style={{ width: "55px", display: "inline-block" }}>To:</span>
            {moment(metadata.to.after).format("l LT")} to{" "}
            {moment(metadata.to.before).format("l LT")}
          </div>
        </>
      );
    case "applied_price_diff":
      return money(metadata.diff);
  }
}

function Event({ event }) {
  const { username } = event.createdBy;
  const { t } = useTranslation();

  const metadata = _metadataToText(event);

  return (
    <div className="media-body">
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

function _body(event) {
  switch (event.type) {
    case "commented":
      return <Comment event={event} />;
    default:
      return <Event event={event} />;
  }
}

function Item({ event }) {
  const { username } = event.createdBy;
  const eventType = event.type !== "commented";
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

export default connectWithRedux(function () {
  const events = useSelector((state) => state.incident.events);

  return (
    <div className="tl-incident-event">
      {events.map((event) => (
        <Item key={event.id} event={event} />
      ))}
    </div>
  );
});
