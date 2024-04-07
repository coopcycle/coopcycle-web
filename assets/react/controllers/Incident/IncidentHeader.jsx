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

export default function ({ incident, createdBy }) {
  incident = JSON.parse(incident);
  const [priority, setPriority] = useState(incident.priority);
  const [status, setStatus] = useState(incident.status);
  const [tags, setTags] = useState(incident.tags);

  const statusBtn = () => {
    switch (status) {
      case "OPEN":
        return { next: "CLOSED", label: "Close this incident" };
      case "CLOSED":
        return { next: "OPEN", label: "Reopen this incident" };
    }
  };

  return (
    <PageHeader
      title={incident.title}
      extra={[
        <Dropdown.Button
          key="close"
          onClick={() => {
            const next = statusBtn().next;
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
              { label: "Set to low priority", key: 1 },
              { label: "Set to medium priority", key: 2 },
              { label: "Set to high priority", key: 3 },
            ],
          }}
          type="primary"
        >
          {statusBtn().label}
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
          value={priority}
          style={{ margin: "0 22px" }}
        />
        <Statistic title="Created by" value={createdBy} />
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
