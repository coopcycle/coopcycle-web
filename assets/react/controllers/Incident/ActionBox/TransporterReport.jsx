import React, { useState, useEffect, useMemo } from "react";
import classNames from "classnames";
import {
  Alert,
  Select,
  Form,
  DatePicker,
  Image,
  Checkbox,
  Row,
  Col,
  Divider,
  Empty,
  Skeleton,
  notification,
} from "antd";
import "../Style.scss";
import _ from "lodash";
import moment from "moment";
import { useTranslation } from "react-i18next";

async function _fetchFailureReason(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_tasks_task_failure_reasons_item", { id }),
    { transporter: true },
  );
}

async function _handleFormSubmit(
  { failureReason, failureDate, images },
  { id },
) {
  const httpClient = new window._auth.httpClient();
  const failure_reason = `${failureReason.code.state}|${failureReason.code.reason}`;
  let appointment = null;
  if (failureReason.date) {
    appointment = failureReason.date.toISOString();
  }
  const pods = _.map(images, "full");

  const payload = {
    action: "transporter_reported",
    failure_reason,
    appointment,
    pods,
    created_at: failureDate.toISOString(),
  };

  return await httpClient.put(
    window.Routing.generate("api_incidents_action_item", { id }),
    payload,
  );
}

function ImagesSelector({ images, onChange }) {
  if (images.length === 0) {
    return <Empty description="No images" />;
  }

  const [selectedImages, setSelectedImages] = useState([]);
  const [indeterminate, setIndeterminate] = useState(false);
  const [checkAll, setCheckAll] = useState(false);

  const { t } = useTranslation();

  const handleOnChange = (checkedList) => {
    setCheckAll(checkedList.length === images.length);
    setIndeterminate(
      !!checkedList.length && checkedList.length < images.length,
    );
    setSelectedImages(checkedList);
    onChange(checkedList);
  };

  const onCheckAllChange = (e) => {
    handleOnChange(e.target.checked ? images : []);
    setCheckAll(e.target.checked);
    setIndeterminate(false);
  };

  return (
    <>
      <div>
        {images.length > 1 && (
          <Checkbox
            className="my-3 mx-1"
            indeterminate={indeterminate}
            onChange={onCheckAllChange}
            checked={checkAll}
          >
            {t("CHECK_ALL")}
          </Checkbox>
        )}
      </div>
      <Checkbox.Group onChange={handleOnChange} value={selectedImages}>
        <Row gutter={[12, 12]} align="middle">
          {images.map((image) => {
            const selected = selectedImages.includes(image);
            return (
              <Col key={image.id} align="middle">
                <Image
                  width="128px"
                  height="128px"
                  src={image.thumbnail}
                  preview={{ src: image.full }}
                  className={classNames("p-2, border", {
                    "border-primary": selected,
                    "border-default": !selected,
                  })}
                />
                <div className="mt-1">
                  <Checkbox value={image}>{t("SELECT")}</Checkbox>
                </div>
              </Col>
            );
          })}
        </Row>
      </Checkbox.Group>
    </>
  );
}

function FailureReasonSelector({ task, onChange }) {
  const [failureReasons, setFailureReasons] = useState(null);
  const [selectedState, setSelectedState] = useState(null);
  const [selectedReason, setSelectedReason] = useState(null);
  const [selectedDate, setSelectedDate] = useState(null);

  const { t } = useTranslation();

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchFailureReason(task.id);
      if (!error) {
        const value = response["hydra:member"].map((v) => ({
          value: v.code,
          label: v.description,
          option: v.option ?? "default",
          only: v.only ?? null,
        }));
        setFailureReasons(value);
      }
    }
    _fetch();
  }, []);

  const options = useMemo(
    () => _.groupBy(failureReasons, (v) => v.option),
    [failureReasons],
  );
  const subOptions = useMemo(
    () => _.filter(options.reason, (v) => v.only.includes(selectedState)),
    [options, selectedState],
  );

  useEffect(() => {
    if (!selectedReason || !selectedState) {
      return onChange(null);
    }
    if (selectedReason === "PVI" && !selectedDate) {
      return onChange(null);
    }
    return onChange({
      code: { state: selectedState, reason: selectedReason },
      date: selectedDate,
    });
  }, [selectedState, selectedReason, selectedDate]);

  //TODO: Improve appointment display detection
  return (
    <Skeleton title={false} loading={!failureReasons}>
      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Select
            placeholder={t("SELECT_STATE")}
            value={selectedState}
            onChange={(v) => {
              setSelectedReason(null);
              setSelectedState(v);
            }}
            options={options.state}
          />
        </Col>
        <Col span={12}>
          <Select
            placeholder={t("SELECT_REASON")}
            value={selectedReason}
            onChange={setSelectedReason}
            options={subOptions}
          />
        </Col>
        {selectedReason === "PVI" && (
          <Col span={12} offset={12}>
            <DatePicker
              style={{ width: "100%" }}
              placeholder={t("PICK_NEW_APPOINTMENT")}
              format="LLL"
              showTime={{ format: "HH:mm", minuteStep: 15 }}
              onChange={setSelectedDate}
              disabledDate={(date) => date.isBefore(moment(), "hour")}
            />
          </Col>
        )}
      </Row>
    </Skeleton>
  );
}

export default function ({ incident, task, images, form }) {
  const { t } = useTranslation();

  const incidents = useMemo(
    () =>
      _(incident?.events)
        .filter((event) => event.type === "transporter_report")
        .size(),
    [incident],
  );
  const reasonValidator = (_, value) => {
    if (!value) {
      // Do not validate if there is no value
      return Promise.resolve();
    }
    if (!value.code.reason || !value.code.state) {
      return Promise.reject(t("PLEASE_SELECT_A_REASON_AND_STATE"));
    }
    if (value.code.reason === "PVI" && !moment(value.date).isValid()) {
      return Promise.reject(t("PLEASE_SELECT_A_VALID_DATE"));
    }
    if (value.code.reason === "PVI" && value.date.isBefore(moment())) {
      return Promise.reject(t("PLEASE_SELECT_A_DATE_IN_THE_FUTURE"));
    }
    return Promise.resolve();
  };

  return (
    <Form
      layout="vertical"
      form={form}
      name="transporter-report"
      onFinish={async (values) => {
        const { error } = await _handleFormSubmit(values, incident);
        if (!error) {
          return location.reload();
        }
        notification.error({
          message: t("SOMETHING_WENT_WRONG"),
        });
      }}
      autoComplete="off"
    >
      {incidents > 0 && (
        <Alert
          className="mb-3"
          showIcon
          message={t("THERE_IS_ALREADY_COUNT_REPORTS_FOR_THIS_INCIDENT", {
            count: incidents,
          })}
          type="info"
        />
      )}
      <Form.Item
        label={t("SELECT_REASON")}
        name="failureReason"
        required={true}
        rules={[
          { validator: reasonValidator },
          { required: true, message: t("PLEASE_SELECT_A_REASON") },
        ]}
      >
        <FailureReasonSelector task={task} />
      </Form.Item>
      <Form.Item
        label={t("FAILURE_DATE")}
        name="failureDate"
        extra={t("SELECT_THE_DATE_AND_TIME_OF_THE_INCIDENT")}
        rules={[
          { required: true, message: t("PLEASE_SELECT_A_DATE") },
          { type: "date", message: t("PLEASE_SELECT_A_VALID_DATE") },
        ]}
      >
        <DatePicker
          style={{ width: "100%" }}
          format="LLL"
          showTime={{ format: "HH:mm", minuteStep: 15 }}
          disabledDate={(date) => date.isAfter(moment(), "minute")}
        />
      </Form.Item>
      <Divider orientation="left" plain>
        {t("IMAGES_TO_INCLUDE_IN_THE_REPORT")}
      </Divider>
      <Form.Item name="images">
        <ImagesSelector images={images} />
      </Form.Item>
    </Form>
  );
}
