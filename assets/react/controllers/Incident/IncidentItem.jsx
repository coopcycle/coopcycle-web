import React, { useEffect, useState } from "react";
import { Spin } from "antd";
import TaskStatusBadge from "../../../../js/app/dashboard/components/TaskStatusBadge";
import { money } from "./utils";

async function _fetchTaskContect(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_tasks_get_task_context_item", { id }),
  );
}

function ContextDetails({ delivery, order }) {
  if (order) {
    const orderID = order?.number
      ? `Order N°${order.number}`
      : `Order #${order.id}`;

    return (
      <>
        <hr className="my-2" />
        <p className="font-weight-bold">Order details</p>
        <div>{orderID}</div>
        {order?.restaurant && <div>{order?.restaurant.name}</div>}
        <div>{money(order.itemsTotal)}</div>
        <div>{order?.customer?.fullName ?? order?.customer?.username}</div>
      </>
    );
  }

  if (delivery) {
    return (
      <>
        <hr className="my-2" />
        <p className="font-weight-bold">Delivery details</p>
      </>
    );
  }

  return null;
}

export default function ({ task }) {
  const [context, setContext] = useState(null);

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchTaskContect(task.id);
      if (!error) {
        setContext(response);
      }
    }
    _fetch();
  }, []);

  if (!context) {
    return <Spin />;
  }

  return (
    <div className="p-2">
      <p>
        <span className="font-weight-bold mr-2">Task #{task.id}</span>
        <TaskStatusBadge task={task} />
      </p>
      <div className="text-capitalize">Type: {task.type.toLowerCase()}</div>
      <ContextDetails context={context} />
    </div>
  );
}
