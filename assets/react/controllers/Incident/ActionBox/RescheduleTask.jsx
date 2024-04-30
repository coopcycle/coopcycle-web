import React, { useState } from "react";
import moment from "moment";
import { Button, DatePicker } from "antd";
import { useTranslation } from "react-i18next";

async function _handleResheduleSubmit(id, after, before) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "rescheduled", after, before },
  );
}

export default function ({ incident, task }) {
  const [value, setValue] = useState(null);
  const { t } = useTranslation();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(false);

  const doneAfter = moment(task.after);
  const doneBefore = moment(task.before);
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
        style={{ width: "100%" }}
        format="DD/MM/YYYY HH:mm"
        status={error ? "error" : null}
        ranges={ranges}
        defaultValue={[doneAfter, doneBefore]}
        showTime={{ format: "HH:mm", minuteStep: 15 }}
        onChange={(dates) => setValue(dates)}
      />
      <p className="mt-3">
        <Button
          disabled={value === null || submitting}
          onClick={async () => {
            setSubmitting(true);
            const after = value[0].set({ second: 0, millisecond: 0 });
            const before = value[1].set({ second: 0, millisecond: 0 });
            const { error } = await _handleResheduleSubmit(
              incident.id,
              after.format(),
              before.format(),
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
          {t("ADMIN_DASHBOARD_AND_CLOSE_THE_INCIDENT", {
            action: t("ADMIN_DASHBOARD_RESCHEDULE"),
          })}
        </Button>
      </p>
    </div>
  );
}
