import React from "react";
import TaskTimeline from "../../../../js/app/dashboard/components/TaskTimeline";

export default function ({ events }) {
  events = Object.values(JSON.parse(events));
  return <TaskTimeline isLoadingEvents={false} events={events} />;
}
