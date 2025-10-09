import React from "react";
import TaskTimeline from "../../../../dashboard/components/TaskTimeline";

export default function ({ events }) {
  const _events = Object.values(JSON.parse(events));
  return <TaskTimeline isLoadingEvents={false} events={_events} />;
}
