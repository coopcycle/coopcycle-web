import React from "react";
import moment from "moment";
import { PageHeader, Row, Statistic, Dropdown, Select } from "antd";

export default function ({ incident, createdBy }) {
  incident = JSON.parse(incident);

  return (
    <PageHeader
      onBack={() => window.history.back()}
      title={incident.title}
      extra={[
        <Dropdown.Button
          key="close"
          menu={{
            items: [
              { label: "Set to low priority", value: 1 },
              { label: "Set to medium priority", value: 2 },
              { label: "Set to high priority", value: 3 },
            ],
          }}
          type="primary"
        >
          Close this incident
        </Dropdown.Button>,
      ]}
    >
      <Row className="mb-3">
        <Statistic
          title="Status"
          style={{ textTransform: "capitalize", marginLeft: "42px" }}
          value={incident.status.toLowerCase()}
        />
        <Statistic
          title="Priority"
          value={incident.priority}
          style={{ margin: "0 42px" }}
        />
        <Statistic title="Created by" value={createdBy} />
      </Row>
      <Row justify="space-between" className="mt-3">
        <Select
          mode="tags"
          placeholder="+ Add tags"
          options={[
            { label: "Test", value: "test" },
            { label: "Techical issue", value: "techical-issue" },
          ]}
          style={{ marginLeft: "22px", width: "300px" }}
          bordered={false}
        />
        <div>
          <i className="fa fa-calendar" style={{ marginRight: "5px" }} />
          {moment(incident.createdAt).fromNow()}
        </div>
      </Row>
    </PageHeader>
  );
}
