/**
 * Accessible tab list with a rotation progress bar and a "Paused" label.
 * @param {object} props - Component props.
 * @param {Array<{crs: string, name: string}>} props.destinations - Tabs to show.
 * @param {number} props.activeIndex - Index of the active tab.
 * @param {number} props.progress - Progress 0 to 1 until the next rotation.
 * @param {boolean} props.paused - True while rotation is paused by interaction.
 * @param {function(number): void} props.onSelect - Called with the index of a chosen tab.
 * @returns {JSX.Element} The tab bar.
 */
export default function TabBar({ destinations, activeIndex, progress, paused, onSelect }) {
  /**
   * Arrow key navigation between tabs.
   * @param {import('react').KeyboardEvent} event - Key event.
   */
  const onKeyDown = (event) => {
    if (event.key === 'ArrowRight') onSelect((activeIndex + 1) % destinations.length)
    if (event.key === 'ArrowLeft') onSelect((activeIndex - 1 + destinations.length) % destinations.length)
  }

  return (
    <div className="tabbar">
      <div className="tabbar__list" role="tablist" aria-label="Destinations" onKeyDown={onKeyDown}>
        {destinations.map((d, i) => (
          <button
            key={d.crs}
            type="button"
            role="tab"
            id={`tab-${d.crs}`}
            aria-selected={i === activeIndex}
            aria-controls={`panel-${d.crs}`}
            tabIndex={i === activeIndex ? 0 : -1}
            className="tab"
            onClick={() => onSelect(i)}
          >
            {d.name}
          </button>
        ))}
      </div>
      <progress className="tabbar__progress" max="1000" value={Math.round(progress * 1000)} aria-hidden="true" />
      {paused && <p className="tabbar__paused">Paused</p>}
    </div>
  )
}
