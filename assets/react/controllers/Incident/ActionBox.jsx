import React, { useState } from "react";
import { Button, Drawer, InputNumber } from "antd";
import RescheduleTask from "./ActionBox/RescheduleTask";

export default function ({ incident }) {
  incident = JSON.parse(incident);
  const { task } = incident;
  const { currencySymbol } = document.body.dataset;
  const placement = "left";
  const [open, setOpen] = useState(false);
  const [rescheduleDrawer, setRescheduleDrawer] = useState(false);
  const [priceDiffDrawer, setPriceDiffDrawer] = useState(false);
  return (
    <>
      <Button style={{ width: "100%" }} onClick={() => setOpen(true)}>
        Take actions
      </Button>
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
          <Button onClick={() => setPriceDiffDrawer(true)}>
            Apply a difference on the price
          </Button>
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

        <Drawer
          placement={placement}
          width={500}
          title="Apply a difference on the price"
          onClose={() => setPriceDiffDrawer(false)}
          open={priceDiffDrawer}
        >
          <InputNumber
            addonAfter={currencySymbol}
            size="large"
            style={{ width: "100%" }}
          />
        </Drawer>
      </Drawer>
    </>
  );
}
