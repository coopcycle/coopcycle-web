import React, { useState } from "react";
import { Timeline, Image } from "antd";
import moment from "moment";
import { useTranslation } from "react-i18next";
import {
  DownloadOutlined,
  LoadingOutlined,
  UploadOutlined,
  QuestionCircleOutlined,
} from "@ant-design/icons";

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

function getSitucation(edi) {
  const split = edi?.subMessageType?.split("|");
  if (split.length > 1) {
    return split.shift();
  }
  return null;
}

function ediColor(edi) {
  if (edi.messageType === "SCONTR") {
    return "blue";
  }

  switch (getSitucation(edi)) {
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
    const situation = getSitucation(edi);
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
  let messages = JSON.parse(ediMessages);
  const scontrs = messages.filter((edi) => edi.messageType === "SCONTR");
  const reports = messages.filter((edi) => edi.messageType === "REPORT");
  let scontr = [];
  if (scontrs.length > 1) {
    scontr = [scontrs.shift()];
  }
  messages = [...scontr, ...reports];

  const { t } = useTranslation();
  return (
    <Timeline className="m-3">
      {messages.map((ediMessage) => {
        const { message, dot, color, subMessage } = ediPresenter(ediMessage);
        return (
          <Timeline.Item key={ediMessage.id} dot={dot} color={color}>
            <p>
              {t(message)}
              <span className="text-muted d-block font-weight-light">
                {moment(ediMessage.createdAt).format("l LT")}
              </span>
              {subMessage ? (
                <span className="font-weight-light d-block">
                  {t(subMessage)}
                </span>
              ) : null}
            </p>
            {ediMessage.ediMessage ? (
              <a
                className="mr-3"
                target="_blank"
                rel="noreferrer"
                href={window.Routing.generate("admin_transporter_message", {
                  edi: ediMessage.ediMessage,
                })}
              >
                {t("TRANSPORTER_SHOW_EDI")}
              </a>
            ) : null}
            {ediMessage.pods.length > 0 && (
              <PreviewPods pods={ediMessage.pods} />
            )}
          </Timeline.Item>
        );
      })}
    </Timeline>
  );
}
