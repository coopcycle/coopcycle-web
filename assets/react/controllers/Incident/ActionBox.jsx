import React, { useState } from "react";
import { Button, Drawer, InputNumber, Popconfirm } from "antd";
import RescheduleTask from "./ActionBox/RescheduleTask";

async function _handleCancelButton(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "cancel_task" },
  );
}

export default function ({ incident, task }) {
  incident = JSON.parse(incident);
  task = JSON.parse(task);
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
        {task.status !== "DONE" && (
          <p>
            <Button onClick={() => setRescheduleDrawer(true)}>
              Reschedule the task
            </Button>
          </p>
        )}
        {task.status !== "DONE" ||
          (task.status !== "CANCELLED" && (
            <p>
              <Popconfirm
                placement="rightTop"
                title="Are you sure?"
                onConfirm={async () => {
                  const { error } = await _handleCancelButton(incident.id);
                  if (!error) {
                    location.reload();
                  }
                }}
              >
                <Button>Cancel the task</Button>
              </Popconfirm>
            </p>
          ))}
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
          <RescheduleTask task={task} incident={incident} />
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
