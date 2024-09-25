import React, { useEffect, useState } from "react";
import { Select, Radio, Spin, notification } from "antd";
import { DragDropContext, Draggable, Droppable } from "react-beautiful-dnd";
import _ from 'lodash';
import { useTranslation } from 'react-i18next';

import 'skeleton-screen-css/dist/index.scss'

async function _fetchTimeSlots() {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_time_slots_get_collection", { pagination: new Boolean(false).toString() }),
  );
}

async function _putTimeSlots(id, data) {
  const httpClient = new window._auth.httpClient();
  const uri = window.Routing.generate('api_stores_get_item', { id })

  return await httpClient.patch(uri, {
    '@id': uri,
    ...data
  });
}

async function _updateTimeSlots(id, timeSlots, setFetching) {

  let payload = { timeSlots }
  // If all timeslots are removed, we also clear the default one
  if (timeSlots.length === 0) {
    payload = { ...payload, timeSlot: null }
  }

  setFetching(true)
  const { error } = await _putTimeSlots(id, payload);
  setFetching(false)

  if (error && error.response) {
    notification.error({
      message: error.response.data['hydra:title'],
      description: error.response.data['hydra:description'],
    });
  }
}

function reOrderRow(array, from, to) {
  const rows = Array.from(array);
  const [removed] = rows.splice(from, 1);
  rows.splice(to, 0, removed);
  return rows;
}

function removeValue(choices, value) {
  return choices.filter(({ value: v }) => v !== value);
}

const Row = ({ row, index, setSelectedTS, selectedTS, setFetching, id }) => {

  const { t } = useTranslation()

  return (
    <Draggable draggableId={index.toString()} index={index}>
      {(provided) => (
        <p key={index} ref={provided.innerRef} {...provided.draggableProps}>
          <i {...provided.dragHandleProps} className="fa fa-bars mr-1"></i>
          <i
            className="fa fa-minus-circle"
            onClick={() => {
              const timeSlots = removeValue(selectedTS, row.value)
              setSelectedTS(timeSlots)
              _updateTimeSlots(id, timeSlots.map(({ value }) => value), setFetching)
            }}
            aria-hidden="true"
          ></i>
          <span className="mx-2" style={{ display: "inline-flex" }}>
            {row.label}
          </span>
          <Radio style={{ float: "right" }} value={row.value}>
            { t('TIME_SLOTS_SET_DEFAULT') }
          </Radio>
        </p>
      )}
    </Draggable>
  );
}

export default function ({ store }) {

  const { id, timeSlot, timeSlots } = JSON.parse(store);

  const { t } = useTranslation()

  const [choices, setChoices] = useState(null);
  const [selectedTS, setSelectedTS] = useState(null);
  const [defaultTS, setDefaultTS] = useState(timeSlot);
  const [selectValue, setSelectValue] = useState(null);
  const [fetching, setFetching] = useState(false);

  useEffect(() => {
    async function _fetch() {
      setFetching(true)
      const { response, error } = await _fetchTimeSlots();
      setFetching(false)
      if (!error) {
        const choices = response["hydra:member"].map((ts) => ({
          value: ts["@id"],
          label: ts.name,
        }));
        setChoices(choices);

        // Make sure we use the expected order
        setSelectedTS(timeSlots.map(ts => choices.find(choice => choice.value === ts)));
      }
    }
    _fetch();
  }, []);

  if (!choices && !selectedTS) {
    return (
      <div>
        <h5>{ t('TIME_SLOTS') }</h5>
        <div>
          <div className="ssc-head-line mb-3" style={{ width: '30%' }}></div>
          { timeSlots.map((ts, key) => <div key={ key } className="ssc-line mb-2"></div>) }
        </div>
      </div>
    );
  }

  return (
    <Spin spinning={fetching}>
      <h5>{ t('TIME_SLOTS') }</h5>
      <div>
        <Select
          className="my-3 mr-2"
          value={selectValue}
          options={choices}
          onChange={(_value, selectedOption) => {
            const timeSlots = _.uniqBy([selectedOption, ...selectedTS], 'value');
            setSelectValue(null)
            setSelectedTS(timeSlots);
            _updateTimeSlots(id, timeSlots.map(({ value }) => value), setFetching)
          }}
          placeholder={ t('TIME_SLOTS_SELECT') }
        />
      </div>
      <DragDropContext
        onDragEnd={ async ({ source, destination }) => {
          const reordered = reOrderRow(
            selectedTS,
            source.index,
            destination.index,
          );
          setSelectedTS(reordered);
          _updateTimeSlots(id, reordered.map(({ value }) => value), setFetching)
        }}
      >
        <Radio.Group
          style={{ width: "100%", fontSize: "inherit" }}
          onChange={async (e) => {
            setDefaultTS(e.target.value);
            setFetching(true)
            const { error } = await _putTimeSlots(id, {
              timeSlot: e.target.value,
            });
            setFetching(false)
            if (error && error.response) {
              notification.error({
                message: error.response.data['hydra:title'],
                description: error.response.data['hydra:description'],
              });
            }
          }}
          value={defaultTS}
        >
          <Droppable direction="vertical" droppableId="droppable">
            {({ droppableProps, innerRef, placeholder }) => (
              <div {...droppableProps} ref={innerRef}>
                { selectedTS.map((row, index) =>
                  <Row key={ index } row={ row } index={ index } id={ id }
                    setSelectedTS={ setSelectedTS } selectedTS={ selectedTS } setFetching={ setFetching } />
                )}
                { placeholder }
              </div>
            )}
          </Droppable>
        </Radio.Group>
      </DragDropContext>
    </Spin>
  );
}
