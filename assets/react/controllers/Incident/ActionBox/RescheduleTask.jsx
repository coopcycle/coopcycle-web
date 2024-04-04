import React, { useState } from 'react'
import moment from 'moment'
import {Button, DatePicker} from 'antd'
import { useTranslation } from "react-i18next";

export default function ({ task }) {
  const [value, setValue] = useState(null);
  const { t } = useTranslation();

  const doneAfter = moment(task.doneAfter);
  const doneBefore = moment(task.doneBefore);
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
          ranges={ranges}
          defaultValue={[doneAfter, doneBefore]}
          showTime={{ format: "HH:mm" }}
          onChange={(dates) => setValue(dates)}
        />
      <p className='mt-3'>
        <Button
          disabled={value === null}
          onClick={() =>
            console.log(task, value[0].format(), value[1].format())
          }
        >
          {t("ADMIN_DASHBOARD_RESCHEDULE")}
        </Button>
      </p>
      <p>
        <Button type="danger" ghost disabled={value === null}>
          {t("ADMIN_DASHBOARD_RESCHEDULE")}{" "}
          {t("ADMIN_DASHBOARD_AND_CLOSE_THE_INCIDENT")}
        </Button>
      </p>
    </div>
  );
}
