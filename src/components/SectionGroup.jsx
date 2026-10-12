import ServiceCard from './ServiceCard.jsx'

/**
 * A heading plus either a grid of cards or a calm placeholder message.
 * @param {object} props - Component props.
 * @param {import('../utils/timeWindows.js').SectionSpec} props.section - Section to render.
 * @param {string} props.destinationName - Name of the tab destination.
 * @param {number} props.lateThreshold - Minutes late treated as on time.
 * @param {boolean} props.loading - True while the first load is still pending.
 * @returns {JSX.Element} The section.
 */
export default function SectionGroup({ section, destinationName, lateThreshold, loading }) {
  const headingId = `section-${section.key}`
  let body
  if (section.placeholder) {
    body = <p className="placeholder">{section.placeholder}</p>
  } else if (loading) {
    body = <p className="placeholder">Loading trains…</p>
  } else if (section.services.length === 0) {
    body = <p className="placeholder">{section.emptyMessage}</p>
  } else {
    body = (
      <div className="card-grid">
        {section.services.map((service) => (
          <ServiceCard key={service.id} service={service} destinationName={destinationName} lateThreshold={lateThreshold} />
        ))}
      </div>
    )
  }
  return (
    <section className="section" aria-labelledby={headingId}>
      <h2 id={headingId} className="section__title">
        {section.title}
      </h2>
      {body}
    </section>
  )
}
