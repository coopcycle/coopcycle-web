import React, { useState, useEffect } from "react";
import { connect } from "react-redux";
import { selectSelectedTasks } from "../redux/selectors";
import { Select, Skeleton, Form, Input, Button, notification } from "antd";
import { useTranslation } from "react-i18next";

async function _fetchFailureReason(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_tasks_task_failure_reasons_item", { id }),
  );
}

async function _createIncident(task, data) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.post(
    window.Routing.generate("api_incidents_get_collection"),
    {
      task: task["@id"],
      ...data,
    },
  );
}

function FailureReasonSelector({ task, onChange, value }) {
  const [failureReasons, setFailureReasons] = useState(null);

  const { t } = useTranslation();

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchFailureReason(task.id);
      if (!error) {
        const value = response["hydra:member"].map((v) => ({
          value: v.code,
          label: v.description,
        }));
        setFailureReasons(value);
      }
    }
    _fetch();
  }, []);

  return (
    <Skeleton title={false} loading={!failureReasons}>
      <Select
        placeholder={t("SELECT_REASON")}
        value={value}
        onChange={onChange}
        options={failureReasons}
      />
    </Skeleton>
  );
}

function TaskReportIncidentModalContent({ task }) {
  const [loading, setLoading] = useState(false);
  const [incident, setIncident] = useState(null);
  const { t } = useTranslation();

  return (
    <Form
      layout="vertical"
      onFinish={async (data) => {
        setLoading(true);
        const { response, error } = await _createIncident(task, data);
        if (!error) {
          setIncident(response);
        } else {
          notification.error({
            message: t("SOMETHING_WENT_WRONG"),
          });
        }
        setLoading(false);
      }}
    >
      <Form.Item label={t("FAILURE_REASON")} name="failureReasonCode">
        <FailureReasonSelector task={task} />
      </Form.Item>

      <Form.Item label="Description" name="description">
        <Input.TextArea placeholder="Description" autoSize={{ minRows: 2 }} />
      </Form.Item>
      <Form.Item>
        <Button type="primary" loading={loading} htmlType="submit">
          {t("REPORT")}
        </Button>
        {incident && (
          <a
            className="ml-3"
            target="_blank"
            rel="noopener noreferrer"
            href={window.Routing.generate("admin_incident", {
              id: incident.id,
            })}
          >
            {t("VIEW_INCIDENT")}
            <i
              className="fa fa-external-link ml-1"
              style={{ fontSize: "12px" }}
            />
          </a>
        )}
      </Form.Item>
    </Form>
  );
}

function mapStateToProps(state) {
  return {
    task: selectSelectedTasks(state).shift(),
  };
}

function mapDispatchToProps() {
  return {};
}

export default connect(
  mapStateToProps,
  mapDispatchToProps,
)(TaskReportIncidentModalContent);
