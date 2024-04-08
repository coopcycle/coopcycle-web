import React from "react";
import moment from "moment";
import { Button } from "antd";
import "./OrderDetails.scss";
import { money } from "./utils";
import TaskStatusBadge from "../../../../js/app/dashboard/components/TaskStatusBadge";

import store from "./incidentStore";

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

function heading(task, delivery, order) {
  const header = (title, btn = null) => (
    <h4 style={{ lineHeight: "24px" }}>
      {title}
      {btn}
    </h4>
  );
  if (order?.number) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`Order N° ${order.number}`, _externalLink(link));
  }

  if (order?.id) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`Order #${order.id}`, _externalLink(link));
  }

  if (delivery?.id) {
    const link = window.Routing.generate("admin_delivery", { id: order.id });
    return header(`Delivery #${delivery.id}`, _externalLink(link));
  }

  return header(`Task #${task.id}`);
}

function customerShowName(customer) {
  let customerName = customer?.username;
  if (customer?.fullName != null) {
    customerName = customer.fullName;
  }
  return (
    <p title={customer?.username}>
      Customer<span>{customerName}</span>
    </p>
  );
}

function showAdjustment(adjustments, adjustmentType) {
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

function showOrderDetails(order) {
  return (
    <>
      <h5>Order details</h5>
      <p>
        Subtotal<span>{money(order.itemsTotal)}</span>
      </p>
      {showAdjustment(order.adjustments, "delivery")}
      {showAdjustment(order.adjustments, "tax")}
      {showAdjustment(order.adjustments, "incident")}
      <p>
        Total<span>{money(order.total)}</span>
      </p>
      <hr />
    </>
  );
}

function showCustomerDetails(customer) {
  const link = window.Routing.generate("admin_user_edit", {
    username: customer?.username,
  });
  return (
    <>
      <h5 style={{ lineHeight: "24px" }}>
        Customer details{_externalLink(link)}
      </h5>
      {customerShowName(customer)}
      {customer?.email && (
        <p>
          Email<span>{customer?.email}</span>
        </p>
      )}
      {customer?.telephone && (
        <p>
          Phone<span>{customer?.telephone}</span>
        </p>
      )}
    </>
  );
}

export default function ({ delivery }) {
  delivery = JSON.parse(delivery);
  const { loaded, order, incident } = store.getState();
  const { task } = incident;

  if (!loaded) {
    return null;
  }

  return (
    <div className="order-details-card">
      {heading(task, delivery, order)}
      <p className="text-muted">Date: {formatTime(task)}</p>
      <hr />
      {order && showOrderDetails(order)}
      <h5>
        <span style={{ textTransform: "capitalize" }}>
          {task.type.toLowerCase()}
        </span>{" "}
        details
      </h5>
      <p>{task.address.name}</p>
      <p>{task.address.streetAddress}</p>
      <p>{task.address.telephone}</p>
      {task.weight && <p>{task.weight} kg</p>}
      <div className="mt-3">{<TaskStatusBadge task={task} />}</div>
      <hr />
      {order?.customer && showCustomerDetails(order.customer)}
    </div>
  );
}
