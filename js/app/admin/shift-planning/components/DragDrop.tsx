import React, { useEffect, useRef, useState } from 'react';
import {
  draggable,
  dropTargetForElements,
  monitorForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter';

/**
 * Shared drag'n'drop wiring for the planning grids (EmployeeGrid,
 * ActivityGrid): a shift card, dropped on a different cell, moves there.
 * Built on @atlaskit/pragmatic-drag-and-drop — same lib as the menu editor
 * (js/app/restaurant/menu-editor.js) — rather than a heavier list-oriented
 * lib, since a cell holds a handful of cards with no reordering needed, just
 * "this card belongs to this cell now".
 */

export type CellData = Record<string, string>;

type DropCellProps = {
  /** Identifies the cell to whoever handles the drop; e.g. { userUri, dayKey } */
  data: CellData;
  className?: string;
  onClick?: () => void;
  children?: React.ReactNode;
};

export function DropCell({ data, className, onClick, children }: DropCellProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [isDraggingOver, setIsDraggingOver] = useState(false);
  // Rebind whenever the cell's identity changes (new day/week) rather than
  // over stale `data` captured in the getData() closure
  const dataKey = JSON.stringify(data);

  useEffect(() => {
    const el = ref.current;
    if (!el) {
      return;
    }
    return dropTargetForElements({
      element: el,
      getData: () => data,
      onDragEnter: () => setIsDraggingOver(true),
      onDragLeave: () => setIsDraggingOver(false),
      onDrop: () => setIsDraggingOver(false),
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dataKey]);

  return (
    <div
      ref={ref}
      className={`${className ?? ''} ${
        isDraggingOver ? 'shift-planning__cell--drag-over' : ''
      }`}
      onClick={onClick}>
      {children}
    </div>
  );
}

type DraggableCardProps = {
  /** Identifies the dragged card to whoever handles the drop; e.g. { shiftUri, userUri } */
  data: CellData;
  children: React.ReactNode;
};

export function DraggableCard({ data, children }: DraggableCardProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [isDragging, setIsDragging] = useState(false);
  const dataKey = JSON.stringify(data);

  useEffect(() => {
    const el = ref.current;
    if (!el) {
      return;
    }
    return draggable({
      element: el,
      getInitialData: () => data,
      onDragStart: () => setIsDragging(true),
      onDrop: () => setIsDragging(false),
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dataKey]);

  return (
    <div
      ref={ref}
      className={
        isDragging ? 'shift-planning__draggable shift-planning__draggable--dragging' : 'shift-planning__draggable'
      }>
      {children}
    </div>
  );
}

/**
 * Fires `onMove(source, destination)` whenever a DraggableCard is dropped on
 * a DropCell, with their respective `data`. Grids interpret those shapes
 * themselves — this hook is agnostic to what they contain.
 */
export function useCellDropMonitor(
  onMove: (source: CellData, destination: CellData) => void,
) {
  useEffect(() => {
    return monitorForElements({
      onDrop: ({ source, location }) => {
        const destination = location.current.dropTargets[0]?.data as
          | CellData
          | undefined;
        if (!destination) {
          return;
        }
        onMove(source.data as CellData, destination);
      },
    });
  }, [onMove]);
}
