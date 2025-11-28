import { v4 as uuidv4 } from 'uuid';

export const generateTempId = (): string => `temp-${uuidv4()}`;

// check if a task ID is temporary (not from backend)
export const isTemporaryId = (taskId: string | null): boolean => {
  return taskId !== null && taskId.startsWith('temp-');
};
