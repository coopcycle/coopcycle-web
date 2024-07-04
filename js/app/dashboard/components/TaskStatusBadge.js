import React from "react";
import classNames from "classnames";

export default function ({ task }) {
  const { status } = task;
  return (
    <span
      style={{
        borderRadius: "18px",
        padding: "2px 8px",
        border: "1px dashed #d9d9d9",
        textTransform: "capitalize",
        fontSize: "0.9em",
      }}
    >
      <i
        className={classNames("fa mr-2", {
          "fa-clock-o text-default": status == "TODO",
          "fa-bicycle text-info": status == "DOING",
          "fa-exclamation-triangle text-warning": status == "FAILED",
          "fa-check text-success": status == "DONE",
          "fa-times text-danger": status == "CANCELLED",
        })}
      ></i>
      {status.toLowerCase()}
    </span>
  );
}
