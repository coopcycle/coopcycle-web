import React, { useEffect, useRef, useState } from "react";
import { Timeline, Image } from "antd";
import moment from "moment";
import { useTranslation } from "react-i18next";
import JsBarcode from "jsbarcode";
import {
  DownloadOutlined,
  LoadingOutlined,
  UploadOutlined,
  QuestionCircleOutlined,
} from "@ant-design/icons";
import { EDIFACTMessage, ReportSituation } from "../../../api/types";

function ediDot(edi: EDIFACTMessage) {
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

function getSituation(edi: EDIFACTMessage): ReportSituation | null {
  const split = edi.subMessageType?.split("|");
  if (split && split.length > 1) {
    const code = split.shift();
    if (code) {
      return code as ReportSituation;
    }
  }
  return null;
}

function ediColor(edi: EDIFACTMessage) {
  if (edi.messageType === "SCONTR") {
    return "blue";
  }

  switch (getSituation(edi)) {
    // Successful delivery / proof of delivery
    case "LIV":
    case "POD":
    case "POP":
      return "green";
    // Failure: refused, returned, not delivered, unrealized pickup
    case "REN":
    case "RST":
    case "ENE":
      return "red";
    // Warning: delays, recipient absent
    case "DIF":
    case "EDI":
      return "orange";
    // In progress
    case "MLV":
    case "EML":
    case "ECH":
    case "PAQ":
    case "PCH":
    case "AAR":
    case "CHG":
    case "DCH":
    case "EXP":
    case "SEQ":
    case "SOL":
      return "blue";
    default:
      return "gray";
  }
}

function ediPresenter(edi: EDIFACTMessage) {
  const situation = getSituation(edi);
  return {
    message: `TRANSPORTER_MESSAGE_TYPE_${edi.messageType}`,
    subMessage: situation ? `TRANSPORTER_SITUATION_${situation}` : null,
    dot: ediDot(edi),
    color: ediColor(edi),
  };
}

function PreviewPods({ pods }: { pods: string[] }) {
  const [visible, setVisible] = useState(false);
  const { t } = useTranslation();

  return (
    <>
      <Image.PreviewGroup
        preview={{ visible, onVisibleChange: setVisible }}
      >
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

function ImportBarcode({ reference }: { reference: string }) {
  const svgRef = useRef<SVGSVGElement | null>(null);

  useEffect(() => {
    if (svgRef.current && reference) {
      try {
        JsBarcode(svgRef.current, reference, {
          format: "CODE128",
          height: 60,
          displayValue: true,
        });
      } catch (e) {
        // ignore invalid reference
      }
    }
  }, [reference]);

  return (
    <svg
      ref={svgRef}
      className="barcode img-thumbnail img-responsive center-block"
    />
  );
}

type Props = {
  ediMessages: EDIFACTMessage[];
  importReference?: string | null;
};

export function TransporterTimeline({ ediMessages, importReference }: Props) {
  const { t } = useTranslation();

  const scontrs = ediMessages.filter((edi) => edi.messageType === "SCONTR");
  const reports = ediMessages.filter((edi) => edi.messageType === "REPORT");
  const scontr = scontrs.length > 1 ? [scontrs.shift() as EDIFACTMessage] : [];
  const messages = [...scontr, ...reports];

  const timelineItems = messages.map((ediMessage) => {
    const { message, dot, color, subMessage } = ediPresenter(ediMessage);
    return {
      key: String(ediMessage.id),
      dot,
      color,
      children: (
        <>
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
        </>
      ),
    };
  });

  return (
    <div>
      {importReference ? <ImportBarcode reference={importReference} /> : null}
      <h4>{t("TRANSPORTER_EVENT_TIMELINE")}</h4>
      <Timeline className="m-3" items={timelineItems} />
    </div>
  );
}

export default TransporterTimeline;
