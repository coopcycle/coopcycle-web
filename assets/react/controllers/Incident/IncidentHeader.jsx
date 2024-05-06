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
import { useTranslation } from "react-i18next";

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

function _prioryToLabel(key) {
  switch (key) {
    case 1:
      return "HIGH";
    case 2:
      return "MEDIUM";
    case 3:
      return "LOW";
  }
}

function _statusBtn(status) {
  switch (status) {
    case "OPEN":
      return {
        next: "CLOSED",
        label: "CLOSE_THIS_INCIDENT",
        icon: <i className="fa fa-dot-circle-o mr-2" />,
      };
    case "CLOSED":
      return {
        next: "OPEN",
        label: "REOPEN_THIS_INCIDENT",
        icon: <i className="fa fa-check-circle-o mr-2" />,
      };
  }
}

export default function () {
  const { loaded, incident } = store.getState();
  const [priority, setPriority] = useState(incident.priority);
  const [status, setStatus] = useState(incident.status);
  const [tags, setTags] = useState(incident.tags);

  const { t } = useTranslation();

  if (!loaded) {
    return null;
  }

  const { next, label, icon } = _statusBtn(status);

  return (
    <PageHeader
      title={incident.title}
      extra={[
        <Dropdown.Button
          key="close"
          onClick={() => {
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
              {
                label: t("SET_TO_PRIORITY", {
                  priority: t("LOW").toLowerCase(),
                }),
                key: 3,
              },
              {
                label: t("SET_TO_PRIORITY", {
                  priority: t("MEDIUM").toLowerCase(),
                }),
                key: 2,
              },
              {
                label: t("SET_TO_PRIORITY", {
                  priority: t("HIGH").toLowerCase(),
                }),
                key: 1,
              },
            ],
          }}
          type="primary"
        >
          {icon}
          {t(label)}
        </Dropdown.Button>,
      ]}
    >
      <Row className="mb-3">
        <Statistic
          title={t("STATUS")}
          style={{ textTransform: "capitalize", marginLeft: "12px" }}
          value={t(status)}
        />
        <Statistic
          title={t("PRIORITY")}
          value={t(_prioryToLabel(priority))}
          style={{ margin: "0 22px" }}
        />
        <Statistic
          title={t("REPORTED_BY")}
          value={incident.createdBy.username}
        />
      </Row>
      <Row justify="space-between" className="mt-3">
        <Select
          mode="tags"
          placeholder={t("PLUS_ADD_TAGS")}
          onBlur={() => syncData(incident.id, { tags })}
          onChange={(tags) => setTags(tags)}
          options={tags.map((t) => ({ label: t, value: t }))}
          style={{ marginLeft: "2px", width: "300px" }}
          bordered={false}
        />
        <div>
          <div className="pb-1">{t("INCIDENT_REPORTED_AT")} :</div>
          <i className="fa fa-calendar pr-1" />
          {moment(incident.createdAt).format("LLL")}
        </div>
      </Row>
    </PageHeader>
  );
}
