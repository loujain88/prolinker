/**
 * useAdminApi.js  —  src/composables/useAdminApi.js
 *
 * Thin wrapper around the shared Axios instance for all /api/admin/* calls.
 * Import: import adminApi from '@/composables/useAdminApi'
 */
import api from '@/composables/useApi'

const adminApi = {
  // ── Stats ──────────────────────────────────────────────────────────────────
  getStats: () => api.get('/admin/stats'),

  // ── Deposits ───────────────────────────────────────────────────────────────
  getPendingDeposits:  ()           => api.get('/admin/deposits/pending'),
  getAllDeposits:       (params={}) => api.get('/admin/deposits', { params }),
  getDepositReceipt:   (id)         => api.get(`/admin/deposits/${id}/receipt`),
  getWithdrawalReceipt: (id)        => api.get(`/admin/withdrawals/${id}/receipt`),
  getWithdrawalPayoutImage: (id)    => api.get(`/admin/withdrawals/${id}/payout-image`),

  // ── Staff management (super admin only) ───────────────────────────────────
  getStaff:    ()          => api.get('/admin/staff'),
  addStaff:    (payload)   => api.post('/admin/staff', payload),
  removeStaff: (id)        => api.delete(`/admin/staff/${id}`),
  getAccountRequests:    ()   => api.get('/admin/account-requests'),
  getAccountIdDocument:  (id) => api.get(`/admin/account-requests/${id}/id-document`),
  approveAccount:        (id) => api.put(`/admin/account-requests/${id}/approve`),
  rejectAccount:         (id, reason) => api.put(`/admin/account-requests/${id}/reject`, { reason }),
  approveDeposit:      (id, data={}) => api.put(`/admin/deposits/${id}/approve`, data),
  rejectDeposit:       (id, notes)  => api.put(`/admin/deposits/${id}/reject`, { notes }),

  // ── Withdrawals ────────────────────────────────────────────────────────────
  getPendingWithdrawals: ()           => api.get('/admin/withdrawals/pending'),
  getAllWithdrawals:      (params={}) => api.get('/admin/withdrawals', { params }),
  approveWithdrawal: (id, formData) => {
    formData.append('_method', 'PUT') // Laravel method spoofing — required for multipart file uploads
    return api.post(`/admin/withdrawals/${id}/approve`, formData, {
      headers: { 'Content-Type': undefined },
    })
  },
  rejectWithdrawal: (id, notes) => api.put(`/admin/withdrawals/${id}/reject`, { notes }),

  // ── Services ───────────────────────────────────────────────────────────────
  getPendingServices:  ()            => api.get('/admin/services/pending'),
  getAllServices:       (params={}) => api.get('/admin/services', { params }),
  updateServiceStatus: (id, payload) => api.put(`/admin/services/${id}/status`, payload),

  // ── Categories ─────────────────────────────────────────────────────────────
  getCategories:  ()         => api.get('/admin/categories'),
  createCategory: (data)     => api.post('/admin/categories', data),
  updateCategory: (id, data) => api.put(`/admin/categories/${id}`, data),
  deleteCategory: (id)       => api.delete(`/admin/categories/${id}`),

  // ── Users ──────────────────────────────────────────────────────────────────
  getUsers:    (params={}) => api.get('/admin/users', { params }),
  getUser:     (id)        => api.get(`/admin/users/${id}`),
  blockUser:   (id)        => api.put(`/admin/users/${id}/block`),
  unblockUser: (id)        => api.put(`/admin/users/${id}/unblock`),
  deleteUser:  (id)        => api.delete(`/admin/users/${id}`),

  // ── Disputes ───────────────────────────────────────────────────────────────
  getDisputes:      (params={}) => api.get('/admin/disputes', { params }),
  getDispute:       (id)        => api.get(`/admin/disputes/${id}`),
  assignDispute:    (id)        => api.put(`/admin/disputes/${id}/assign`),
  resolveForClient: (id, notes) => api.put(`/admin/disputes/${id}/resolve/client`, { admin_notes: notes }),
  resolveForSeller: (id, notes) => api.put(`/admin/disputes/${id}/resolve/seller`, { admin_notes: notes }),
  resolveSplit:     (id, notes) => api.put(`/admin/disputes/${id}/resolve/split`, { admin_notes: notes }),
  closeDispute:     (id, notes) => api.put(`/admin/disputes/${id}/close`, { admin_notes: notes }),

  // ── Conversations (oversight) ───────────────────────────────────────────────
  getConversations:  ()   => api.get('/admin/conversations'),
  getConversation:   (id) => api.get(`/admin/conversations/${id}`),
}

export default adminApi
