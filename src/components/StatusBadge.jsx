/**
 * Status pill. Colour, icon and text always appear together.
 * @param {object} props - Component props.
 * @param {import('../utils/status.js').StatusInfo} props.info - Display info from getStatusInfo().
 * @returns {JSX.Element} The badge.
 */
export default function StatusBadge({ info }) {
  return (
    <span className={`badge badge--${info.kind}`}>
      <span className="badge__icon" aria-hidden="true">
        {info.icon}
      </span>
      <span className="badge__label">{info.label}</span>
    </span>
  )
}
