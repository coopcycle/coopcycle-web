import React, { useState, useEffect, useMemo } from "react";
import classNames from "classnames";
import {
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
} from "antd";
import "../Style.scss";
import _ from "lodash";
import moment from "moment";

async function _fetchFailureReason(id) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_tasks_task_failure_reasons_item", { id }),
  );
}

function ImagesSelector({ images, onChange }) {
  if (images.length === 0) {
    return <Empty description="No images" />;
  }

  const [selectedImages, setSelectedImages] = useState([]);
  const [indeterminate, setIndeterminate] = useState(false);
  const [checkAll, setCheckAll] = useState(false);

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
            Check all
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
                  <Checkbox value={image}>Select</Checkbox>
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

  return (
    <Skeleton title={false} loading={!failureReasons}>
      <Row gutter={[16, 16]}>
        <Col span={12}>
          <Select
            placeholder="Select state"
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
            placeholder="Select reason"
            value={selectedReason}
            onChange={setSelectedReason}
            options={subOptions}
          />
        </Col>
        {selectedReason === "PVI" && (
          <Col span={12} offset={12}>
            <DatePicker
              style={{ width: "100%" }}
              placeholder="Pick a new appointment"
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
  console.log(incident);

  const _handleFormSubmit = ({ failureReason, failureDate, images }) => {
    const failure_reason = `${failureReason.code.state}|${failureReason.code.reason}`;
    let appointment = null;
    if (failureReason.date) {
      appointment = failureReason.date.toISOString();
    }
    const pods = _.map(images, "full");

    console.log({
      action: "transporter_report",
      failure_reason,
      appointment,
      pods,
      created_at: failureDate.toISOString(),
    });
  };

  const reasonValidator = (_, value) => {
    if (!value) {
      // Do not validate if there is no value
      return Promise.resolve();
    }
    if (!value.code.reason || !value.code.state) {
      return Promise.reject("Please select a reason and state");
    }
    if (value.code.reason === "PVI" && !moment(value.date).isValid()) {
      return Promise.reject("Please select a valid date");
    }
    if (value.code.reason === "PVI" && value.date.isBefore(moment())) {
      return Promise.reject("Please select a date in the future");
    }
    return Promise.resolve();
  };

  return (
    <Form
      layout="vertical"
      form={form}
      name="transporter-report"
      onFinish={_handleFormSubmit}
      autoComplete="off"
    >
      <Form.Item
        label="Failure reason"
        name="failureReason"
        required={true}
        rules={[
          { validator: reasonValidator },
          { required: true, message: "Please select a reason" },
        ]}
      >
        <FailureReasonSelector task={task} />
      </Form.Item>
      <Form.Item
        label="Failure date"
        name="failureDate"
        extra="Select the date and time of the incident."
        rules={[
          { required: true, message: "Please select a date" },
          { type: "date", message: "Please select a valid date" },
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
        Images to include in the report
      </Divider>
      <Form.Item name="images">
        <ImagesSelector images={images} />
      </Form.Item>
    </Form>
  );
}
