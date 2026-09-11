import { baseQueryWithReauth } from '../../../api/baseQuery';
import { RootState } from './store';
import { ThunkAction, UnknownAction } from '@reduxjs/toolkit';

export type SettlementFilter = 'needs_invoicing' | 'settled' | 'all';

export function prepareParams({
  organization,
  dateRange,
  state,
  onlyNotInvoiced,
  settlement,
}: {
  organization?: string[];
  dateRange: string[];
  state?: string[];
  onlyNotInvoiced: boolean;
  settlement?: SettlementFilter;
}): string[] {
  let params = [];

  if (organization && organization.length > 0) {
    params.push(
      ...organization.map(
        organizationId => `organization[]=${organizationId}`,
      ),
    );
  }

  if (state && state.length > 0) {
    params.push(...state.map(state => `state[]=${state}`));
  }

  params.push(`date[after]=${dateRange[0]}`);
  params.push(`date[before]=${dateRange[1]}`);

  if (onlyNotInvoiced) {
    params.push('exists[exports]=false');
  }

  if (settlement && settlement !== 'all') {
    params.push(`settlement=${settlement}`);
  }

  return params;
}

function downloadFile({
  requestUrl,
  filename,
}: {
  requestUrl: string;
  filename: string;
}): ThunkAction<Promise<void>, RootState, unknown, UnknownAction> {
  return async (dispatch, getState) => {
    const result = await baseQueryWithReauth(
      {
        url: requestUrl,
        headers: {
          Accept: 'text/csv',
        },
        responseHandler: 'text',
      },
      {
        dispatch,
        getState,
      },
    );

    if (result.error) {
      console.warn('error', result.error);
      return;
    }

    const requestId = result.meta.response.headers.get('X-Request-ID');

    const blob = new Blob([result.data], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.download = `${filename}_${requestId.substring(0, 7)}.csv`;
    link.href = url;
    link.click();

    URL.revokeObjectURL(url);
  };
}

export function downloadStandardFile({
  params,
  filename,
}: {
  params: string[];
  filename: string;
}): ThunkAction<Promise<void>, RootState, unknown, UnknownAction> {
  return downloadFile({
    requestUrl: `api/invoice_line_items/export?${params.join('&')}`,
    filename,
  });
}

export function downloadOdooFile({
  params,
  filename,
}: {
  params: string[];
  filename: string;
}): ThunkAction<Promise<void>, RootState, unknown, UnknownAction> {
  return downloadFile({
    requestUrl: `api/invoice_line_items/export/odoo?${params.join('&')}`,
    filename,
  });
}
