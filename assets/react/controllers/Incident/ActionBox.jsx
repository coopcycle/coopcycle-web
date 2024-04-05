import React, { useState } from "react";
import { Button, Divider, Drawer, Popconfirm, Modal } from "antd";
import RescheduleTask from "./ActionBox/RescheduleTask";
import ApplyPriceDiffTask from "./ActionBox/ApplyPriceDiffTask";
import TransporterReport from "./ActionBox/TransporterReport";

async function _handleCancelButton(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "cancel_task" },
  );
}

const styles = {
  btn: {
    width: "100%",
  },
};

export default function ({ incident, task, order, images }) {
  incident = JSON.parse(incident);
  task = JSON.parse(task);
  order = JSON.parse(order);
  images = JSON.parse(images);

  const placement = "left";
  const [open, setOpen] = useState(false);

  const [rescheduleDrawer, setRescheduleDrawer] = useState(false);
  const [priceDiffDrawer, setPriceDiffDrawer] = useState(false);
  const [transporterReportModal, setTransporterReportModal] = useState(false);

  return (
    <>
      <Button style={styles.btn} onClick={() => setOpen(true)}>
        Take actions
      </Button>
      <Drawer
        placement={placement}
        title="Take actions"
        onClose={() => setOpen(false)}
        open={open}
      >
        {task.status !== "DONE" && (
          <>
            <p>
              <Button
                style={styles.btn}
                onClick={() => setRescheduleDrawer(true)}
              >
                Reschedule the task
              </Button>
            </p>
            <Divider>OR</Divider>
          </>
        )}
        {task.status !== "DONE" && task.status !== "CANCELLED" && (
          <>
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
                <Button style={styles.btn}>Cancel the task</Button>
              </Popconfirm>
            </p>
            <Divider>OR</Divider>
          </>
        )}
        {order && (
          <>
            <p>
              <Button
                style={styles.btn}
                onClick={() => setPriceDiffDrawer(true)}
              >
                Apply a difference on the price
              </Button>
            </p>
            <Divider>OR</Divider>
          </>
        )}
        <p>
          <Button
            style={styles.btn}
            onClick={() => setTransporterReportModal(true)}
          >
            Send report to transporter
          </Button>
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
          <ApplyPriceDiffTask incident={incident} order={order} />
        </Drawer>

        <Modal
          title="Transporter report"
          open={transporterReportModal}
          onCancel={() => setTransporterReportModal(false)}
        >
          <TransporterReport incident={incident} images={images} task={task} />
        </Modal>
      </Drawer>
    </>
  );
}
