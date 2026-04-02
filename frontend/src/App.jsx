import { Routes, Route, Navigate } from 'react-router-dom'
import ProtectedRoute from './components/ProtectedRoute'

import LoginPage from './pages/auth/LoginPage'
import DashboardPage from './pages/manager/DashboardPage'
import OrdersPage from './pages/cashier/OrdersPage'
import CheckoutPage from './pages/cashier/CheckoutPage'
import SelfOrderPage from './pages/customer/SelfOrderPage'
import ProductsPage from './pages/manager/ProductsPage'
import TablesPage from './pages/manager/TablesPage'
import ReportsPage from './pages/manager/ReportsPage'
import ExpensesPage from './pages/finance/ExpensesPage'
import FinanceSummaryPage from './pages/finance/FinanceSummaryPage'
import RecipePage from './pages/manager/RecipePage'

function App() {
  return (
    <Routes>
      {/* Public routes */}
      <Route path="/login" element={<LoginPage />} />
      <Route path="/order" element={<SelfOrderPage />} />

      {/* Manager routes */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute roles={['head_manager']}>
            <DashboardPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/manager/products"
        element={
          <ProtectedRoute roles={['head_manager']}>
            <ProductsPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/manager/products/:id/recipes"
        element={
          <ProtectedRoute roles={['head_manager']}>
            <RecipePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/manager/tables"
        element={
          <ProtectedRoute roles={['head_manager']}>
            <TablesPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/manager/reports"
        element={
          <ProtectedRoute roles={['head_manager', 'finance']}>
            <ReportsPage />
          </ProtectedRoute>
        }
      />

      {/* Cashier routes */}
      <Route
        path="/cashier/orders"
        element={
          <ProtectedRoute roles={['kasir']}>
            <OrdersPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/cashier/checkout"
        element={
          <ProtectedRoute roles={['kasir']}>
            <CheckoutPage />
          </ProtectedRoute>
        }
      />

      {/* Finance routes */}
      <Route
        path="/finance/expenses"
        element={
          <ProtectedRoute roles={['finance']}>
            <ExpensesPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/finance/summary"
        element={
          <ProtectedRoute roles={['finance', 'head_manager']}>
            <FinanceSummaryPage />
          </ProtectedRoute>
        }
      />

      {/* Fallback */}
      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  )
}

export default App
