import React, { useState } from "react";
import { Button, Divider, Drawer, Popconfirm, Modal, Form } from "antd";
import RescheduleTask from "./ActionBox/RescheduleTask";
import ApplyPriceDiffTask from "./ActionBox/ApplyPriceDiffTask";
import TransporterReport from "./ActionBox/TransporterReport";

import { useTranslation } from "react-i18next";

import store from "./incidentStore";

async function _handleCancelButton(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    { action: "cancelled" },
  );
}

const styles = {
  btn: {
    width: "100%",
  },
};

export default function ({isLastmile}) {
  const { loaded, incident, order, images, transporterEnabled } =
    store.getState();

  const { t } = useTranslation();

  if (!loaded) {
    return null;
  }

  const { task } = incident;

  const placement = "left";
  const [open, setOpen] = useState(false);

  const [rescheduleDrawer, setRescheduleDrawer] = useState(false);
  const [priceDiffDrawer, setPriceDiffDrawer] = useState(false);
  const [transporterReportModal, setTransporterReportModal] = useState(false);

  const [transporterForm] = Form.useForm();

  const buttons = [
    {
      key: "reschedule",
      component: () => (
        <Button style={styles.btn} onClick={() => setRescheduleDrawer(true)}>
          {t("RESCHEDULE_THE_TASK")}
        </Button>
      ),
      shouldRender: task.status !== "DONE",
    },
    {
      key: "cancel",
      component: () => (
        <Popconfirm
          placement="rightTop"
          title={t("ARE_YOU_SURE")}
          onConfirm={async () => {
            const { error } = await _handleCancelButton(incident.id);
            if (!error) {
              location.reload();
            }
          }}
        >
          <Button style={styles.btn}>{t("CANCEL_THE_TASK")}</Button>
        </Popconfirm>
      ),
      shouldRender: task.status !== "DONE" && task.status !== "CANCELLED",
    },
    {
      key: "apply-price-diff",
      component: () => (
        <Button style={styles.btn} onClick={() => setPriceDiffDrawer(true)}>
          {t("APPLY_A_PRICE_DIFFERENCE")}
        </Button>
      ),
      shouldRender: isLastmile && order && order.state !== "cancelled",
    },
    {
      key: "transporter-report",
      component: () => (
        <Button
          style={styles.btn}
          onClick={() => setTransporterReportModal(true)}
        >
          {t("SEND_REPORT_TO_THE_TRANSPORTER")}
        </Button>
      ),
      shouldRender: transporterEnabled,
    },
  ]
    .filter((b) => b.shouldRender)
    .map((b, index) => (
      <React.Fragment key={b.key}>
        {!!index && <Divider>{t("OR")}</Divider>}
        <p>{b.component()}</p>
      </React.Fragment>
    ));

  return (
    <>
      <Button
        style={styles.btn}
        onClick={() => setOpen(true)}
        disabled={buttons.length === 0}
      >
        {t("TAKE_ACTIONS")}
      </Button>
      <Drawer
        placement={placement}
        title="Take actions"
        onClose={() => setOpen(false)}
        open={open}
      >
        {buttons}
        <Drawer
          placement={placement}
          width={480}
          title={t("RESCHEDULE_THE_TASK")}
          onClose={() => setRescheduleDrawer(false)}
          open={rescheduleDrawer}
        >
          <RescheduleTask task={task} incident={incident} />
        </Drawer>

        <Drawer
          placement={placement}
          width={500}
          title={t("APPLY_A_PRICE_DIFFERENCE")}
          onClose={() => setPriceDiffDrawer(false)}
          open={priceDiffDrawer}
        >
          <ApplyPriceDiffTask incident={incident} order={order} />
        </Drawer>

        <Modal
          width="840px"
          title={t("SEND_REPORT_TO_THE_TRANSPORTER")}
          open={transporterReportModal}
          onOk={() => transporterForm.submit()}
          onCancel={() => setTransporterReportModal(false)}
        >
          <TransporterReport
            incident={incident}
            images={images}
            task={task}
            form={transporterForm}
          />
        </Modal>
      </Drawer>
    </>
  );
}
