import React, { useState } from "react";
import { Timeline, Image } from "antd";
import { useTranslation } from "react-i18next";
import {
  DownloadOutlined,
  LoadingOutlined,
  UploadOutlined,
  QuestionCircleOutlined,
} from "@ant-design/icons";
import classNames from "classnames";

function ediDot(edi) {
  const style = { fontSize: "20px" };
  switch (edi.direction) {
    case "INBOUND":
      return <DownloadOutlined style={style} />;
    case "OUTBOUND":
      switch (edi.syncedAt) {
        case null:
          return <LoadingOutlined style={style} />;
        default:
          return <UploadOutlined style={style} />;
      }
    default:
      return <QuestionCircleOutlined style={style} />;
  }
}

function ediColor(edi) {
  if (edi.messageType === "SCONTR") {
    return "blue";
  }

  switch (edi?.subMessageType?.split("|")[0]) {
    case "LIV":
      return "green";
    case "REN":
    case "RST":
      return "orange";
    default:
      return "blue";
  }
}

function ediPresenter(edi) {
  let subMessage = null;
  if (edi.subMessageType) {
    const situation = edi.subMessageType.split("|")[0];
    subMessage = `TRANSPORTER_SITUATION_${situation}`;
  }
  return {
    message: `TRANSPORTER_MESSAGE_TYPE_${edi.messageType}`,
    subMessage,
    dot: ediDot(edi),
    color: ediColor(edi),
  };
}

function PreviewPods({ pods }) {
  const [visible, setVisible] = useState(false);
  const { t } = useTranslation();

  return (
    <>
      <Image.PreviewGroup preview={{ visible, onVisibleChange: setVisible }}>
        {pods.map((pod, index) => (
          <Image key={index} style={{ display: "none" }} src={pod} />
        ))}
      </Image.PreviewGroup>
      <a
        href="#"
        onClick={(e) => {
          e.preventDefault();
          setVisible(true);
        }}
      >
        {t("TRANSPORTER_SHOW_PODS")}
      </a>
    </>
  );
}

export default function ({ ediMessages }) {
  ediMessages = JSON.parse(ediMessages);
  const scontrs = ediMessages.filter((edi) => edi.messageType === "SCONTR");
  const reports = ediMessages.filter((edi) => edi.messageType === "REPORT");
  let scontr = [];
  if (scontrs.length > 1) {
    scontr = [scontrs.shift()];
  }
  ediMessages = [...scontr, ...reports];

  const { t } = useTranslation();
  return (
    <Timeline className="m-3">
      {ediMessages.map((ediMessage) => {
        const { message, dot, color, subMessage } = ediPresenter(ediMessage);
        return (
          <Timeline.Item key={ediMessage.id} dot={dot} color={color}>
            <p>
              {t(message)}
              <span className="text-muted d-block font-weight-light">
                {new Date(ediMessage.createdAt).toLocaleString()}
              </span>
              {subMessage && (
                <span className="font-weight-light d-block">
                  {t(subMessage)}
                </span>
              )}
            </p>
            {ediMessage.ediMessage && (
              <a
                className="mr-3"
                href={window.Routing.generate("admin_transporter_message", {
                  edi: ediMessage.ediMessage,
                })}
              >
                {t("TRANSPORTER_SHOW_EDI")}
              </a>
            )}
            {ediMessage.pods.length > 0 && (
              <PreviewPods pods={ediMessage.pods} />
            )}
          </Timeline.Item>
        );
      })}
    </Timeline>
  );
}
