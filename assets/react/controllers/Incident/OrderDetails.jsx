import React from "react";
import moment from "moment";
import { Button } from "antd";
import "./OrderDetails.scss";
import { money, weight } from "./utils";
import TaskStatusBadge from "../../../../js/app/dashboard/components/TaskStatusBadge";

import store from "./incidentStore";
import { useTranslation } from "react-i18next";

function formatTime(task) {
  return moment(task.after).format("LL");
}

function _externalLink(link) {
  return (
    <Button
      onClick={() => window.open(link, "_blank")}
      type="dashed"
      style={{ float: "right" }}
      shape="circle"
      size="small"
      icon={<i className="fa fa-external-link" style={{ fontSize: "12px" }} />}
    />
  );
}

function Heading({ task, delivery, order }) {
  const { t } = useTranslation();
  const header = (title, btn = null) => (
    <h4 style={{ lineHeight: "24px" }}>
      {title}
      {btn}
    </h4>
  );

  if (order?.number) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`${t("ORDER")} N° ${order.number}`, _externalLink(link));
  }

  if (order?.id) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`${t("ORDER")} #${order.id}`, _externalLink(link));
  }

  if (delivery?.id) {
    const link = window.Routing.generate("admin_delivery", { id: delivery.id });
    return header(`${t("DELIVERY")} #${delivery.id}`, _externalLink(link));
  }

  return header(`${t("TASK")} #${task.id}`);
}

function CustomerName({ customer }) {
  const { t } = useTranslation();
  let customerName = customer?.username;
  if (customer?.fullName != null) {
    customerName = customer.fullName;
  }
  return (
    <p title={customer?.username}>
      {t("CUSTOMER")}
      <span>{customerName}</span>
    </p>
  );
}

function Adjustment(adjustments, adjustmentType) {
  const total = adjustments[adjustmentType].reduce(
    (total, adjustment) => total + adjustment.amount,
    0,
  );
  if (total === 0) {
    return;
  }
  return adjustments[adjustmentType].map((adjustment) => (
    <p key={adjustment.id}>
      {adjustment.label}
      <span>{money(adjustment.amount)}</span>
    </p>
  ));
}

function OrderDetails({ order }) {
  const { t } = useTranslation();
  return (
    <>
      <h5>{t("ORDER_DETAILS")}</h5>
      <p>
        {t("SUBTOTAL")}
        <span>{money(order.itemsTotal)}</span>
      </p>
      {Adjustment(order.adjustments, "delivery")}
      {Adjustment(order.adjustments, "tax")}
      {Adjustment(order.adjustments, "incident")}
      <p>
        {t("TOTAL")}
        <span>{money(order.total)}</span>
      </p>
      <hr />
    </>
  );
}

function CustomerDetails({ customer }) {
  const { t } = useTranslation();
  const link = window.Routing.generate("admin_user_edit", {
    username: customer?.username,
  });
  return (
    <>
      <h5 style={{ lineHeight: "24px" }}>
        {t("CUSTOMER_DETAILS")}
        {_externalLink(link)}
      </h5>
      <CustomerName customer={customer} />
      {customer?.email && (
        <p>
          {t("EMAIL")}
          <span>{customer?.email}</span>
        </p>
      )}
      {customer?.telephone && (
        <p>
          {t("PHONE")}
          <span>{customer?.telephone}</span>
        </p>
      )}
    </>
  );
}

export default function ({ delivery }) {
  delivery = JSON.parse(delivery);
  const { t } = useTranslation();
  const { loaded, order, incident } = store.getState();
  const { task } = incident;

  if (!loaded) {
    return null;
  }

  return (
    <div className="order-details-card">
      <Heading task={task} delivery={delivery} order={order} />
      <p className="text-muted">
        {t("DATE")}: {formatTime(task)}
      </p>
      <hr />
      {order && <OrderDetails order={order} />}
      <h5>
        <span style={{ textTransform: "capitalize" }}>
          {task.type.toLowerCase()}
        </span>{" "}
        details
      </h5>
      <p>{task.address.name}</p>
      <p>{task.address.streetAddress}</p>
      <p>{task.address.telephone}</p>
      {task.weight && <p>{weight(task.weight)}</p>}
      <div className="mt-3">{<TaskStatusBadge task={task} />}</div>
      <hr />
      {order?.customer && <CustomerDetails customer={order.customer} />}
    </div>
  );
}
