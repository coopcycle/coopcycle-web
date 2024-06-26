import React, { useEffect, useState } from "react";
import { Select, Radio, notification } from "antd";
import { DragDropContext, Draggable, Droppable } from "react-beautiful-dnd";
import _ from 'lodash';

async function _fetchTimeSlots() {
  const httpClient = new window._auth.httpClient();
  return await httpClient.get(
    window.Routing.generate("api_time_slots_get_collection"),
  );
}

async function _putTimeSlots(id, data) {
  const httpClient = new window._auth.httpClient();
  return await httpClient.patch(
    window.Routing.generate("api_stores_get_item", { id }),
    data,
  );
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

const Row = ({ row, index, setSelectedTS, selectedTS }) => (
  <Draggable draggableId={index.toString()} index={index}>
    {(provided) => (
      <p key={index} ref={provided.innerRef} {...provided.draggableProps}>
        <i {...provided.dragHandleProps} className="fa fa-bars mr-1"></i>
        <i
          className="fa fa-minus-circle"
          onClick={() => setSelectedTS(removeValue(selectedTS, row.value))}
          aria-hidden="true"
        ></i>
        <span className="mx-2" style={{ display: "inline-flex" }}>
          {row.label}
        </span>
        <a href="#" className="mr-2">
          <i className="fa fa-external-link" aria-hidden="true"></i>
        </a>
        <Radio style={{ float: "right" }} value={row.value}>
          Set default
        </Radio>
      </p>
    )}
  </Draggable>
);

export default function ({ store }) {

  const { id, timeSlot, timeSlots } = JSON.parse(store);

  const [choices, setChoices] = useState(null);
  const [selectedTS, setSelectedTS] = useState(null);
  const [defaultTS, setDefaultTS] = useState(timeSlot);
  const [selectValue, setSelectValue] = useState(null);

  useEffect(() => {
    async function _fetch() {
      const { response, error } = await _fetchTimeSlots();
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
    return <p>Loading...</p>;
  }

  return (
    <>
      <div className="my-1">Time Slot</div>
      <div>
        <Select
          className="my-3"
          value={selectValue}
          options={choices}
          onChange={(_value, selectedOption) => {
            setSelectValue(null)
            setSelectedTS(_.uniqBy([selectedOption, ...selectedTS], 'value'));
          }}
          placeholder="Select time slot"
        />
      </div>
      <DragDropContext
        onDragEnd={async ({ source, destination }) => {
          const reordered = reOrderRow(
            selectedTS,
            source.index,
            destination.index,
          );
          setSelectedTS(reordered);
          const { error } = _putTimeSlots(id, { timeSlots: reordered.map(({ value }) => value) });
          if (error) {
            notification.error("cpt");
          }
        }}
      >
        <Radio.Group
          style={{ width: "100%", fontSize: "inherit" }}
          onChange={async (e) => {
            setDefaultTS(e.target.value);
            const { error } = await _putTimeSlots(id, {
              timeSlot: e.target.value,
            });
            if (error) {
              notification.error("cpt");
            }
          }}
          value={defaultTS}
        >
          <Droppable direction="vertical" droppableId="droppable">
            {({ droppableProps, innerRef }) => (
              <div {...droppableProps} ref={innerRef}>
                { selectedTS.map((row, index) =>
                  <Row key={ index } row={ row } index={ index }
                    setSelectedTS={ setSelectedTS } selectedTS={ selectedTS } />
                )}
              </div>
            )}
          </Droppable>
        </Radio.Group>
      </DragDropContext>
    </>
  );
}
