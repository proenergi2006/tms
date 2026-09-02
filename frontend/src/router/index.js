/**
 * router/index.js
 *
 * Manual routes for ./src/pages/*.vue
 */

// Composables
import { createRouter, createWebHistory } from 'vue-router'
import DefaultLayout from '@/layouts/DefaultLayout.vue'
import ApprovalStepsList from '@/pages/approval-steps/ApprovalStepsList.vue'
import ApprovalsQueue from '@/pages/approvals/ApprovalsQueue.vue'
import AssetsList from '@/pages/assets/AssetsList.vue'
import AuditLogsList from '@/pages/audit-logs/AuditLogsList.vue'
import FleetDetail from '@/pages/fleets/FleetDetail.vue'
import FleetsList from '@/pages/fleets/FleetsList.vue'
import Index from '@/pages/index.vue'
import Login from '@/pages/login.vue'
import MasterDataHub from '@/pages/master-data/MasterDataHub.vue'
import NotificationsList from '@/pages/notifications/NotificationsList.vue'
import RbacManagement from '@/pages/rbac/RbacManagement.vue'
import FleetProfitabilityReport from '@/pages/reports/FleetProfitabilityReport.vue'
import RequestForm from '@/pages/requests/RequestForm.vue'
import RequestsList from '@/pages/requests/RequestsList.vue'
import Sso from '@/pages/Sso.vue'
import SystemLogsList from '@/pages/system-logs/SystemLogsList.vue'
import UsersManagement from '@/pages/users/UsersManagement.vue'
import WorkOrderDetail from '@/pages/work-orders/WorkOrderDetail.vue'
import WorkOrderPrint from '@/pages/work-orders/WorkOrderPrint.vue'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      component: Login,
      meta: { requiresAuth: false },
    },
    {
      // Dituju dari link SSO SYOP (?token=...) — lihat Sso.vue.
      path: '/sso',
      component: Sso,
      meta: { requiresAuth: false },
    },
    {
      // Standalone (bukan child DefaultLayout) supaya tidak ada
      // sidebar/topbar yang ikut tercetak — lihat WorkOrderPrint.vue.
      path: '/work-orders/:id/print',
      component: WorkOrderPrint,
      props: true,
      meta: { requiresAuth: true },
    },
    {
      path: '/',
      component: DefaultLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', component: Index },
        { path: 'requests', component: RequestsList },
        { path: 'requests/new', component: RequestForm },
        { path: 'requests/:id/edit', component: RequestForm, props: true },
        { path: 'requests/:id/resubmit', component: RequestForm, props: true },
        { path: 'work-orders/:id', component: WorkOrderDetail, props: true },
        { path: 'approvals', component: ApprovalsQueue },
        { path: 'fleets', component: FleetsList },
        { path: 'fleets/:id', component: FleetDetail, props: true },
        { path: 'master-data', component: MasterDataHub },
        { path: 'assets', component: AssetsList },
        { path: 'reports/fleet-profitability', component: FleetProfitabilityReport },
        { path: 'notifications', component: NotificationsList },
        { path: 'audit-logs', component: AuditLogsList },
        { path: 'system-logs', component: SystemLogsList },
        { path: 'rbac', component: RbacManagement },
        { path: 'users', component: UsersManagement },
        { path: 'approval-steps', component: ApprovalStepsList },
      ],
    },
  ],
})

// Route guard berbasis autentikasi (RBAC granular per menu/aksi dilakukan di
// level komponen/permission, lihat Architecture Document Bagian 3.1 & 7.2).
router.beforeEach(to => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth !== false && !auth.isAuthenticated) {
    return { path: '/login' }
  }
})

export default router
