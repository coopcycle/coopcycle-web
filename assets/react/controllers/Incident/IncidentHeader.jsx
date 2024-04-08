import React, { useState } from "react";
import moment from "moment";
import {
  PageHeader,
  Row,
  Statistic,
  Dropdown,
  Select,
  notification,
} from "antd";

import store from "./incidentStore";

async function _handleStatusSubmit(id, body) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.patch(
    window.Routing.generate("api_incidents_get_item", { id }),
    body,
  );
}
async function syncData(id, body) {
  const { error } = await _handleStatusSubmit(id, body);
  if (error) {
    notification.error({ message: "Something went wrong" });
  }
}

function _priorytyLabel(key) {
  switch (key) {
    case 1:
      return "High";
    case 2:
      return "Medium";
    case 3:
      return "Low";
  }
}

function _statusBtn(status) {
  switch (status) {
    case "OPEN":
      return { next: "CLOSED", label: "Close this incident" };
    case "CLOSED":
      return { next: "OPEN", label: "Reopen this incident" };
  }
}

export default function () {
  const { loaded, incident } = store.getState();
  const [priority, setPriority] = useState(incident.priority);
  const [status, setStatus] = useState(incident.status);
  const [tags, setTags] = useState(incident.tags);

  if (!loaded) {
    return null;
  }

  return (
    <PageHeader
      title={incident.title}
      extra={[
        <Dropdown.Button
          key="close"
          onClick={() => {
            const next = _statusBtn(status).next;
            setStatus(next);
            syncData(incident.id, { status: next });
          }}
          menu={{
            onClick: ({ key }) => {
              key = parseInt(key);
              setPriority(key);
              syncData(incident.id, { priority: key });
            },
            items: [
              { label: "Set to low priority", key: 3 },
              { label: "Set to medium priority", key: 2 },
              { label: "Set to high priority", key: 1 },
            ],
          }}
          type="primary"
        >
          {_statusBtn(status).label}
        </Dropdown.Button>,
      ]}
    >
      <Row className="mb-3">
        <Statistic
          title="Status"
          style={{ textTransform: "capitalize", marginLeft: "12px" }}
          value={status.toLowerCase()}
        />
        <Statistic
          title="Priority"
          value={_priorytyLabel(priority)}
          style={{ margin: "0 22px" }}
        />
        <Statistic title="Created by" value={incident.createdBy.username} />
      </Row>
      <Row justify="space-between" className="mt-3">
        <Select
          mode="tags"
          placeholder="+ Add tags"
          onBlur={() => syncData(incident.id, { tags })}
          onChange={(tags) => setTags(tags)}
          options={tags.map((t) => ({ label: t, value: t }))}
          style={{ marginLeft: "2px", width: "300px" }}
          bordered={false}
        />
        <div>
          <div className="pb-1">Incident reported at :</div>
          <i className="fa fa-calendar pr-1" />
          {moment(incident.createdAt).format("LLL")}
        </div>
      </Row>
    </PageHeader>
  );
}
