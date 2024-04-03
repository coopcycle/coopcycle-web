import React from "react";
import moment from "moment";
import { Button } from "antd";
import "./OrderDetails.scss";

function money(amount, { coutry, currencyCode }) {
  return new Intl.NumberFormat(coutry, {
    style: "currency",
    currency: currencyCode,
  }).format(amount / 100);
}

function formatTime(task) {
  return moment(task.after).format("LL");
}

function heading(task, delivery, order) {
  const button = (link) => (
    <Button
      onClick={() => window.open(link, "_blank")}
      type="dashed"
      style={{ float: "right" }}
      shape="circle"
      size="small"
      icon={<i className="fa fa-external-link" style={{ fontSize: "12px" }} />}
    />
  );
  const header = (title, btn = null) => (
    <h4 style={{ lineHeight: "24px" }}>
      {title}
      {btn}
    </h4>
  );
  if (order?.number) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`Order N° ${order.number}`, button(link));
  }

  if (order?.id) {
    const link = window.Routing.generate("admin_order", { id: order.id });
    return header(`Order #${order.id}`, button(link));
  }

  if (delivery?.id) {
    const link = window.Routing.generate("admin_delivery", { id: order.id });
    return header(`Delivery #${delivery.id}`, button(link));
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

function showAdjustement(adjustments, adjustmentType, config) {
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
      <span>{money(adjustment.amount, config)}</span>
    </p>
  ));
}

function showOrderDetails(order, config) {
  return (
    <>
      <h5>Order Details</h5>
      <p>
        Subtotal<span>{money(order.itemsTotal, config)}</span>
      </p>
      {showAdjustement(order.adjustments, "delivery", config)}
      {showAdjustement(order.adjustments, "tax", config)}
      <p>
        Total<span>{money(order.total, config)}</span>
      </p>
      <hr />
    </>
  );
}

function showCustomerDetails(customer) {
  return (
    <>
      <h5>Customer information</h5>
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

export default function ({ task, delivery, order }) {
  task = JSON.parse(task);
  delivery = JSON.parse(delivery);
  order = JSON.parse(order);

  const { coutry, currencyCode } = document.body.dataset;

  return (
    <div className="order-details-card">
      {heading(task, delivery, order)}
      <p className="text-muted">Date: {formatTime(task)}</p>
      <hr />
      {order && showOrderDetails(order, { coutry, currencyCode })}
      <h5>Shipping information</h5>
      <p>{task.address.name}</p>
      <p>{task.address.streetAddress}</p>
      <p>{task.address.telephone}</p>
      {task.weight && <p>{task.weight} kg</p>}
      <hr />
      {order?.customer && showCustomerDetails(order.customer)}
    </div>
  );
}
