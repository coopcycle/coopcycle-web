export function formatTaskNumber(task) {
  return task.metadata?.order_number
    ? task.metadata?.delivery_position
      ? `${task.metadata.order_number}-${task.metadata.delivery_position}`
      : task.metadata.order_number
    : `#${task.id}`;
}
