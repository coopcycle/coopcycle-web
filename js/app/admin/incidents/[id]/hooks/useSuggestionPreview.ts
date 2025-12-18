import { IncidentMetadataSuggestion, TaskPayload } from '../../../../api/types';

export function useSuggestionPreview(suggestion: IncidentMetadataSuggestion) {
  const preview: Partial<IncidentMetadataSuggestion> = {};

  // Copy tasks only if there are fields other than 'id'
  if (suggestion.tasks && suggestion.tasks.length > 0) {
    preview.tasks = suggestion.tasks
      .map(task => {
        const taskKeys = Object.keys(task).filter(key => key !== 'id');

        if (taskKeys.length > 0) {
          const taskCopy = { ...task };
          delete taskCopy.id;
          return taskCopy;
        }
        return null;
      })
      .filter((task): task is TaskPayload => task !== null);

    // If no tasks passed the filter, remove the tasks property
    if (preview.tasks.length === 0) {
      delete preview.tasks;
    }
  }

  // Copy order only if there are fields
  if (suggestion.order) {
    const hasFields = Object.keys(suggestion.order).length > 0;

    if (hasFields) {
      preview.order = { ...suggestion.order };
    }
  }

  return preview;
}
