import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import dayjs from 'dayjs';
import { monitorForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { usePutShiftMutation, usePostShiftMutation } from '../../../../api/slice';
import EmployeeGrid from '../EmployeeGrid';
import ActivityGrid from '../ActivityGrid';
import CalendarGrid from '../CalendarGrid';

jest.mock('@atlaskit/pragmatic-drag-and-drop/element/adapter', () => ({
  draggable: jest.fn(() => () => {}),
  dropTargetForElements: jest.fn(() => () => {}),
  monitorForElements: jest.fn(() => () => {}),
}));
jest.mock('../../../../api/slice', () => ({
  usePutShiftMutation: jest.fn(),
  usePostShiftMutation: jest.fn(),
  useGetEmployeeProfilesQuery: () => ({ data: [] }),
}));
jest.mock('react-i18next', () => ({ useTranslation: () => ({ t: key => key }) }));
jest.mock('antd', () => ({
  App: { useApp: () => ({ message: { success: jest.fn(), error: jest.fn() } }) },
  Tooltip: ({ children }) => children,
  Button: ({ children, onClick }) => <button onClick={onClick}>{children}</button>,
  Select: () => null,
}));
jest.mock('../../../../components/Avatar', () => () => null);
jest.mock('../ShiftCard', () => () => <span>Shift</span>);
jest.mock('../OpenSlotCard', () => () => null);
jest.mock('../HolidayBar', () => () => null);

const mounted = [];
function render(element) {
  const container = document.createElement('div');
  document.body.appendChild(container);
  const root = createRoot(container);
  act(() => root.render(element));
  mounted.push({ root, container });
  return { container };
}
function cleanup() {
  mounted.splice(0).forEach(({ root, container }) => {
    act(() => root.unmount());
    container.remove();
  });
}
global.IS_REACT_ACT_ENVIRONMENT = true;

const user = { '@id': '/api/users/1', username: 'Rider' };
const shift = {
  '@id': '/api/shifts/1', id: 1, activity: 'delivery',
  startsAt: '2026-10-19T09:00:00', endsAt: '2026-10-19T17:00:00',
  slots: 2, breakMinutes: 30, comment: 'Bring a cargo bike',
  requiredSkills: [{ '@id': '/api/skills/1', name: 'Cargo' }],
  assignments: [{ user }], waitlist: [],
};
const common = {
  weekStart: dayjs('2026-10-19'), shifts: [shift], onCreate: jest.fn(), onEdit: jest.fn(),
  activities: [{ slug: 'delivery', name: 'Delivery' }, { slug: 'packing', name: 'Packing' }],
};
let putShift;
let postShift;
beforeEach(() => {
  putShift = jest.fn(() => ({ unwrap: () => Promise.resolve() }));
  postShift = jest.fn(() => ({ unwrap: () => Promise.resolve() }));
  usePutShiftMutation.mockReturnValue([putShift]);
  usePostShiftMutation.mockReturnValue([postShift]);
});
afterEach(cleanup);

const grids = [
  ['employee', EmployeeGrid,
    { users: [user], allUsers: [user], holidayRequests: [], onAddUser: jest.fn(), onRemoveUser: jest.fn() },
    { shiftUri: shift['@id'], userUri: user['@id'], dayKey: '2026-10-19' },
    { userUri: '/api/users/2', dayKey: '2026-10-20' },
    { activity: 'delivery', users: ['/api/users/2'] }],
  ['activity', ActivityGrid, {},
    { shiftUri: shift['@id'], activity: 'delivery', dayKey: '2026-10-19' },
    { activity: 'packing', dayKey: '2026-10-20' },
    { activity: 'packing', users: [user['@id']] }],
];

describe.each(grids)('%s grid drops', (_name, Grid, props, source, destination, expected) => {
  async function drop(input, targets = [{ data: destination }]) {
    render(<Grid {...common} {...props} />);
    const onDrop = monitorForElements.mock.calls[monitorForElements.mock.calls.length - 1][0].onDrop;
    await act(async () => onDrop({ source: { data: source }, location: { current: { dropTargets: targets, input } } }));
  }

  it.each(['shiftKey', 'altKey'])('duplicates on %s without updating the original', async modifier => {
    const original = JSON.stringify(shift);
    await drop({ shiftKey: false, altKey: false, [modifier]: true });
    expect(putShift).not.toHaveBeenCalled();
    expect(postShift).toHaveBeenCalledWith({
      ...expected, startsAt: '2026-10-20T09:00:00', endsAt: '2026-10-20T17:00:00',
      slots: 2, breakMinutes: 30, comment: shift.comment, requiredSkills: ['/api/skills/1'],
    });
    expect(JSON.stringify(shift)).toBe(original);
  });

  it('keeps ordinary dragging as a move', async () => {
    await drop({ shiftKey: false, altKey: false });
    expect(postShift).not.toHaveBeenCalled();
    expect(putShift).toHaveBeenCalledWith(expect.objectContaining({ '@id': shift['@id'], ...expected }));
  });

  it('does not create anything when dropped outside a cell', async () => {
    await drop({ shiftKey: true, altKey: false }, []);
    expect(postShift).not.toHaveBeenCalled();
    expect(putShift).not.toHaveBeenCalled();
  });
});

function pointer(target, type, values) {
  const event = new MouseEvent(type, { bubbles: true, cancelable: true, button: 0, ...values });
  Object.defineProperty(event, 'pointerId', { value: 1 });
  act(() => target.dispatchEvent(event));
}

async function dragCalendar(modifier = {}, resize = false) {
  const { container } = render(<CalendarGrid {...common} />);
  const body = container.querySelector('.shift-planning-calendar__body');
  body.getBoundingClientRect = () => ({ left: 0, width: 756 });
  const block = container.querySelector('.shift-planning-calendar__block');
  const handle = resize ? block.querySelector('.shift-planning-calendar__resize-handle--bottom') : block;
  handle.setPointerCapture = jest.fn();
  pointer(handle, 'pointerdown', { clientX: 106, clientY: 100 });
  pointer(block, 'pointermove', { clientX: 206, clientY: 148 });
  await act(async () => pointer(block, 'pointerup', { clientX: 206, clientY: 148, ...modifier }));
}

describe('calendar drag modifiers', () => {
  it.each(['shiftKey', 'altKey'])('duplicates a move on %s', async modifier => {
    await dragCalendar({ [modifier]: true });
    expect(putShift).not.toHaveBeenCalled();
    expect(postShift).toHaveBeenCalledWith({
      activity: 'delivery', startsAt: '2026-10-20T10:00:00', endsAt: '2026-10-20T18:00:00',
      slots: 2, breakMinutes: 30, comment: shift.comment, users: [user['@id']], requiredSkills: ['/api/skills/1'],
    });
  });

  it('keeps ordinary moves as updates', async () => {
    await dragCalendar();
    expect(postShift).not.toHaveBeenCalled();
    expect(putShift).toHaveBeenCalledWith(expect.objectContaining({ '@id': shift['@id'] }));
  });

  it('resizes rather than duplicating even with a modifier', async () => {
    await dragCalendar({ shiftKey: true }, true);
    expect(postShift).not.toHaveBeenCalled();
    expect(putShift).toHaveBeenCalledWith(expect.objectContaining({ '@id': shift['@id'], startsAt: shift.startsAt, endsAt: '2026-10-19T18:00:00' }));
  });
});
