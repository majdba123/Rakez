/**
 * Map UI/localized contract status to API filter value for GET /api/contracts/admin-index
 * Used by project_management and admin (contracts.view_all).
 * API accepts: pending | approved | rejected | completed
 */

const UI_TO_API_STATUS = {
  pending: 'pending',
  approved: 'approved',
  rejected: 'rejected',
  completed: 'completed',
  // Arabic labels → API
  'قيد الانتظار': 'pending',
  'معتمد': 'approved',
  'مرفوض': 'rejected',
  'مكتمل': 'completed',
};

/**
 * @param {string} [uiStatus] - Status from UI (e.g. dropdown or localized label)
 * @returns {string|undefined} - API status value or undefined to skip filter
 */
export function mapStatusForApi(uiStatus) {
  if (uiStatus == null || uiStatus === '') return undefined;
  const s = String(uiStatus).trim();
  return UI_TO_API_STATUS[s] ?? s;
}

export default mapStatusForApi;
