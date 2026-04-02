/**
 * Reusable Table component.
 *
 * Props:
 *   columns  — array of { key, label }
 *   data     — array of row objects
 *   actions  — optional render function: (row) => ReactNode
 */
const Table = ({ columns = [], data = [], actions }) => {
  const hasActions = typeof actions === 'function'

  return (
    <div className="overflow-x-auto rounded-lg border border-gray-200">
      <table className="min-w-full divide-y divide-gray-200 text-sm">
        <thead className="bg-gray-50">
          <tr>
            {columns.map((col) => (
              <th
                key={col.key}
                scope="col"
                className="px-4 py-3 text-left font-medium text-gray-600 uppercase tracking-wide text-xs"
              >
                {col.label}
              </th>
            ))}
            {hasActions && (
              <th scope="col" className="px-4 py-3 text-right font-medium text-gray-600 uppercase tracking-wide text-xs">
                Aksi
              </th>
            )}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100 bg-white">
          {data.length === 0 ? (
            <tr>
              <td
                colSpan={columns.length + (hasActions ? 1 : 0)}
                className="px-4 py-8 text-center text-gray-400"
              >
                Tidak ada data
              </td>
            </tr>
          ) : (
            data.map((row, idx) => (
              <tr key={row.id ?? idx} className="hover:bg-gray-50 transition-colors">
                {columns.map((col) => (
                  <td key={col.key} className="px-4 py-3 text-gray-700 whitespace-nowrap">
                    {row[col.key] ?? '—'}
                  </td>
                ))}
                {hasActions && (
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    {actions(row)}
                  </td>
                )}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  )
}

export default Table
