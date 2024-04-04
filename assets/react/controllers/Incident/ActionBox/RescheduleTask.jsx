import React, { useState } from "react";
import moment from "moment";
import { Button, DatePicker } from "antd";
import { useTranslation } from "react-i18next";

async function _handleResheduleSubmit(id, after, before) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "reschedule", after, before },
  );
}

export default function ({ incident, task }) {
  const [value, setValue] = useState(null);
  const { t } = useTranslation();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  const doneAfter = moment(task.done_after);
  const doneBefore = moment(task.done_before);
  const ranges = {
    "Next day": [
      doneAfter.clone().add(1, "days"),
      doneBefore.clone().add(1, "days"),
    ],
    "Next week": [
      doneAfter.clone().add(7, "days"),
      doneBefore.clone().add(7, "days"),
    ],
  };

  return (
    <div>
      <DatePicker.RangePicker
        size="large"
        status={error ? "error" : null}
        ranges={ranges}
        defaultValue={[doneAfter, doneBefore]}
        showTime={{ format: "HH:mm" }}
        onChange={(dates) => setValue(dates)}
      />
      <p className="mt-3">
        <Button
          disabled={value === null || submitting}
          onClick={async () => {
            setSubmitting(true);
            const { error } = await _handleResheduleSubmit(
              incident.id,
              value[0].format(),
              value[1].format(),
            );
            if (!error) {
              location.reload();
            } else {
              setError(true);
              setSubmitting(false);
            }
          }}
        >
          {t("ADMIN_DASHBOARD_RESCHEDULE")}
        </Button>
      </p>
      <p>
        <Button type="danger" ghost disabled={value === null || submitting}>
          {t("ADMIN_DASHBOARD_RESCHEDULE")}{" "}
          {t("ADMIN_DASHBOARD_AND_CLOSE_THE_INCIDENT")}
        </Button>
      </p>
    </div>
  );
}
