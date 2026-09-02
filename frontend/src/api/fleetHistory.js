import api from '@/plugins/axios'

// Endpoint Riwayat Armada & Laporan — Design Document Bagian 3.5.
export const fleetHistoryApi = {
  maintenanceHistory: (fleetId, params) => api.get(`/fleets/${fleetId}/maintenance-history`, { params }),
  legalDocs: fleetId => api.get(`/fleets/${fleetId}/legal-docs`),
  createLegalDoc: (fleetId, data) => api.post(`/fleets/${fleetId}/legal-docs`, data),
  updateLegalDoc: (fleetId, docId, data) => api.put(`/fleets/${fleetId}/legal-docs/${docId}`, data),
  fuelLogs: (fleetId, params) => api.get(`/fleets/${fleetId}/fuel-logs`, { params }),
  createFuelLog: (fleetId, data) => api.post(`/fleets/${fleetId}/fuel-logs`, data),
  operationalCosts: (fleetId, params) => api.get(`/fleets/${fleetId}/operational-costs`, { params }),
  profitability: (fleetId, params) => api.get(`/fleets/${fleetId}/profitability`, { params }),
  revenues: fleetId => api.get(`/fleets/${fleetId}/revenues`),
  createRevenue: (fleetId, data) => api.post(`/fleets/${fleetId}/revenues`, data),
  costPerKm: (fleetId, params) => api.get(`/fleets/${fleetId}/cost-per-km`, { params }),
  downtimes: fleetId => api.get(`/fleets/${fleetId}/downtimes`),
  components: fleetId => api.get(`/fleets/${fleetId}/components`),
  updateComponent: (fleetId, componentType, data) => api.put(`/fleets/${fleetId}/components/${componentType}`, data),
  markComponentReplaced: (fleetId, componentType) => api.post(`/fleets/${fleetId}/components/${componentType}/mark-replaced`),
}

export const reportsApi = {
  fleetProfitability: params => api.get('/reports/fleet-profitability', { params }),
  // responseType: 'blob' karena responsnya file .xlsx biner, bukan JSON.
  // Diambil lewat axios (bukan window.open/anchor langsung) supaya header
  // Authorization ikut terkirim — token disimpan di memory, bukan cookie,
  // jadi navigasi browser biasa ke URL ini tidak akan terautentikasi.
  fleetProfitabilityExport: params => api.get('/reports/fleet-profitability/export', { params, responseType: 'blob' }),
  fleetMaintenanceCost: params => api.get('/reports/fleet-maintenance-cost', { params }),
}
