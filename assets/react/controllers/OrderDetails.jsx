import React from 'react'
import moment from 'moment'
import './OrderDetails.scss'

//TODO: Make money format dynamic
function money(amount) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR'}).format(amount/100)
}

function formatTime (task) {
  return moment(task.after).format('LL');
}

function heading(task, delivery, order) {
  if (order?.number) {
    return `Order N° ${order.number}`
  }

  if (order?.id) {
    return `Order #${order.id}`
  }

  if (delivery?.id) {
    return `Delivery #${delivery.id}`
  }

  return `Task: #${task.id}`
}

function customerShowName(customer) {
  let customerName = customer?.username
  if (customer?.fullName != null) {
    customerName = customer.fullName
  }
  return <p title={customer?.username}>Customer<span>{customerName}</span></p>
}

function showAdjustement(adjustments, adjustmentType) {
  const total = adjustments[adjustmentType].reduce((total, adjustment) => total + adjustment.amount, 0)
  if (total === 0) {
    return;
  }
  return adjustments[adjustmentType].map(adjustment => (
    <p key={adjustment.id}>{adjustment.label}<span>{money(adjustment.amount)}</span></p>
  ))
}

function showOrderDetails(order) {
      return <><h5>Order Details</h5>
      <p>Subtotal<span>{money(order.itemsTotal)}</span></p>
      {showAdjustement(order.adjustments, 'delivery')}
      {showAdjustement(order.adjustments, 'tax')}
      <p>Total<span>{money(order.total)}</span></p>
      <hr/></>
}

function showCustomerDetails(customer) {
    return <><h5>Customer information</h5>
      {customerShowName(customer)}
      {customer?.email && <p>Email<span>{customer?.email}</span></p>}
      {customer?.telephone && <p>Phone<span>{customer?.telephone}</span></p>}</>

}

export default function ({task, delivery, order}) {
  task = JSON.parse(task)
  delivery = JSON.parse(delivery)
  order = JSON.parse(order)

  console.log(task, delivery, order)

  return (
    <div className="order-details-card">
      <h4>{heading(task, delivery, order)}</h4>
      <p className="text-muted">Date: {formatTime(task)}</p>
      <hr/>
      {order && showOrderDetails(order)}
      <h5>Shipping information</h5>
      <p>{task.address.name}</p>
      <p>{task.address.streetAddress}</p>
      <p>{task.address.telephone}</p>
      {task.weight && <p>{task.weight} kg</p>}
      <hr/>
      {order?.customer && showCustomerDetails(order.customer)}
      </div>
  )
}
