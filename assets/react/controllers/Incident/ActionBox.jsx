import React, { useState } from "react";
import { Button, DatePicker, Drawer } from "antd";
import moment from "moment";
import { useTranslation } from "react-i18next";

function RescheduleTask({ task }) {
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
      <p>
        <DatePicker.RangePicker
          ranges={ranges}
          defaultValue={[doneAfter, doneBefore]}
          showTime={{ format: "HH:mm" }}
          onChange={(dates) => setValue(dates)}
        />
      </p>
      <p>
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

export default function ({ task }) {
  task = JSON.parse(task);
  const placement = "left";
  const [open, setOpen] = useState(false);
  const [rescheduleDrawer, setRescheduleDrawer] = useState(false);
  return (
    <>
      <Button onClick={() => setOpen(true)}>Take actions</Button>
      <Drawer
        placement={placement}
        title="Take actions"
        onClose={() => setOpen(false)}
        open={open}
      >
        <p>
          <Button onClick={() => setRescheduleDrawer(true)}>
            Reschedule the task
          </Button>
        </p>
        <p>
          <Button>Cancel the task</Button>
        </p>
        <p>
          <Button>Apply a difference on the price</Button>
        </p>
        <p>
          <Button>Send report to transporter</Button>
        </p>

        <Drawer
          placement={placement}
          width={500}
          title="Reschedule the task"
          onClose={() => setRescheduleDrawer(false)}
          open={rescheduleDrawer}
        >
          <RescheduleTask task={task} />
        </Drawer>
      </Drawer>
    </>
  );
}
