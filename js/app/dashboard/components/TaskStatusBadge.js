import React from "react";
import classNames from "classnames";

export default function ({ task }) {
  const { status } = task;
  return (
    <span
      className={classNames("label", {
        "label-default": status == "TODO",
        "label-info": status == "DOING",
        "label-warning": status == "FAILED",
        "label-success": status == "DONE",
        "label-danger": status == "CANCELLED",
      })}
    >
      <i
        className={classNames("fa", {
          "fa-clock-o": status == "TODO",
          "fa-bicycle": status == "DOING",
          "fa-exclamation-triangle": status == "FAILED",
          "fa-check": status == "DONE",
          "fa-times": status == "CANCELLED",
        })}
      ></i>{" "}
      {status}
    </span>
  );
}
