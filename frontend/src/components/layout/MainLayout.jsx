import Sidebar from './Sidebar'
import Header from './Header'

const MainLayout = ({ children }) => (
  <div className="flex h-screen overflow-hidden bg-gray-50">
    <Sidebar />

    <div className="flex flex-1 flex-col overflow-hidden">
      <Header />

      <main className="flex-1 overflow-y-auto p-6">
        {children}
      </main>
    </div>
  </div>
)

export default MainLayout
