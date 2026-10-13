/**
 * @fileoverview icons.jsx – icon component library for Next Train From.
 *
 * Exports two categories of icons:
 *  - **Image-based operator logos** – one per UK train operating company,
 *    identified by their two-letter TransportAPI operator code.
 *  - **Emoji-based utility icons** – lightweight inline icons for status,
 *    journey info, and UI affordances.
 *
 * Also exports two helper functions:
 *  - `getStatusIcon(status)` – maps a TransportAPI status string to an icon.
 *  - `getOperatorIcon(operatorCode)` – maps a TOC code to its logo component.
 *
 * All logo components share the same prop signature: `{ size, className, alt }`.
 *
 * @module icons
 * @license CC BY-NC-SA 4.0 https://creativecommons.org/licenses/by-nc-sa/4.0/
 */

// ---------------------------------------------------------------------------
// Image-based operator logo components
// ---------------------------------------------------------------------------

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const BritishRailLogo = ({ size = '2em', className = 'rail-logo', alt = 'British Rail' }) => (
  <img src="/images/britishrail_logo.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const GreatWesternLogo = ({ size = '2em', className = 'operator-logo', alt = 'Great Western Railway' }) => (
  <img src="/images/GW.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const CrossCountryLogo = ({ size = '2em', className = 'operator-logo', alt = 'Cross Country Trains' }) => (
  <img src="/images/XC.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const SouthWesternLogo = ({ size = '2em', className = 'operator-logo', alt = 'South Western Railway' }) => (
  <img src="/images/SW.webp" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const HeathrowExpressLogo = ({ size = '2em', className = 'operator-logo', alt = 'Heathrow Express' }) => (
  <img src="/images/HEX.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const GatwickExpressLogo = ({ size = '2em', className = 'operator-logo', alt = 'Gatwick Express' }) => (
  <img src="/images/GEX.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const TfLLogo = ({ size = '2em', className = 'operator-logo', alt = 'Transport for London' }) => (
  <img src="/images/TfL.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const SouthernLogo = ({ size = '2em', className = 'operator-logo', alt = 'Southern Railway' }) => (
  <img src="/images/SR.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const SouthEasternLogo = ({ size = '2em', className = 'operator-logo', alt = 'South Eastern Railway' }) => (
  <img src="/images/SE.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const AvantiWCLogo = ({ size = '2em', className = 'operator-logo', alt = 'Avanti West Coast' }) => (
  <img src="/images/AV.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const WestMidlandsLogo = ({ size = '2em', className = 'operator-logo', alt = 'West Midlands Railway' }) => (
  <img src="/images/WM.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const OvergroundLogo = ({ size = '2em', className = 'operator-logo', alt = 'London Overground' }) => (
  <img src="/images/LO.webp" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const LNERLogo = ({ size = '2em', className = 'operator-logo', alt = 'London North Eastern Railway' }) => (
  <img src="/images/lner.webp" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const GreatNorthernLogo = ({ size = '2em', className = 'operator-logo', alt = 'Great Northern Railway' }) => (
  <img src="/images/GN.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const GrandCentralLogo = ({ size = '2em', className = 'operator-logo', alt = 'Grand Central Railway' }) => (
  <img src="/images/GC.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const HullTrainsLogo = ({ size = '2em', className = 'operator-logo', alt = 'Hull Trains' }) => (
  <img src="/images/HT.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const GreaterAngliaLogo = ({ size = '2em', className = 'operator-logo', alt = 'Greater Anglia' }) => (
  <img src="/images/GA.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const ChilternRailwaysLogo = ({ size = '2em', className = 'operator-logo', alt = 'Chiltern Railways' }) => (
  <img src="/images/CR.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const NorthernLogo = ({ size = '2em', className = 'operator-logo', alt = 'Northern Railway' }) => (
  <img src="/images/NR.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const TfWLogo = ({ size = '2em', className = 'operator-logo', alt = 'Transport for Wales' }) => (
  <img src="/images/TfW.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const EastMidlandsLogo = ({ size = '2em', className = 'operator-logo', alt = 'East Midlands Railway' }) => (
  <img src="/images/EM.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const TransPennineLogo = ({ size = '2em', className = 'operator-logo', alt = 'TransPennine Express' }) => (
  <img src="/images/TP.png" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const ScotRailLogo = ({ size = '2em', className = 'operator-logo', alt = 'ScotRail' }) => (
  <img src="/images/SCT.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

/** @param {{ size?: string, className?: string, alt?: string }} props */
export const ThamesLinkLogo = ({ size = '2em', className = 'operator-logo', alt = 'Thameslink' }) => (
  <img src="/images/TL.jpg" alt={alt} className={className} style={{ height: size, width: 'auto' }} />
);

// ---------------------------------------------------------------------------
// Emoji-based utility icons
// ---------------------------------------------------------------------------

/** @param {{ size?: string, className?: string }} props */
export const TrainIcon      = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🚂</span>;

/** @param {{ size?: string, className?: string }} props */
export const StationIcon    = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🚉</span>;

/** @param {{ size?: string, className?: string }} props */
export const ClockIcon      = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>⏰</span>;

/** @param {{ size?: string, className?: string }} props */
export const PlatformIcon   = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🛤️</span>;

/** @param {{ size?: string, className?: string }} props */
export const InfoIcon       = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>ℹ️</span>;

/** @param {{ size?: string, className?: string }} props */
export const ScheduleIcon   = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>📋</span>;

/** @param {{ size?: string, className?: string }} props */
export const WarningIcon    = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>⚠️</span>;

/** @param {{ size?: string, className?: string }} props */
export const CheckIcon      = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>✅</span>;

/** @param {{ size?: string, className?: string }} props */
export const LocationIcon   = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>📍</span>;

/** @param {{ size?: string, className?: string }} props */
export const RouteIcon      = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🗺️</span>;

/** @param {{ size?: string, className?: string }} props */
export const OperatorIcon   = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🏢</span>;

/** @param {{ size?: string, className?: string }} props */
export const ArrowRightIcon = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>➡️</span>;

/** @param {{ size?: string, className?: string }} props */
export const OnTimeIcon     = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>✅</span>;

/** @param {{ size?: string, className?: string }} props */
export const EarlyIcon      = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>⚡</span>;

/** @param {{ size?: string, className?: string }} props */
export const LateIcon       = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>⏳</span>;

/** @param {{ size?: string, className?: string }} props */
export const CancelledIcon  = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>❌</span>;

/** @param {{ size?: string, className?: string }} props */
export const StartsHereIcon = ({ size = '1em', className = '' }) => <span className={className} style={{ fontSize: size }}>🚀</span>;

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

/**
 * Return the appropriate status icon for a TransportAPI status string.
 *
 * @param {string} status    - e.g. `'On time'`, `'Late'`, `'Cancelled'`.
 * @param {string} [size='1em']     - CSS font-size passed to the icon component.
 * @param {string} [className='']   - Additional CSS class for the icon wrapper.
 * @returns {JSX.Element}
 */
export const getStatusIcon = (status, size = '1em', className = '') => {
  const s = status.toLowerCase();
  if (s.includes('on time'))     return <OnTimeIcon     size={size} className={className} />;
  if (s.includes('early'))       return <EarlyIcon      size={size} className={className} />;
  if (s.includes('late'))        return <LateIcon       size={size} className={className} />;
  if (s.includes('cancelled'))   return <CancelledIcon  size={size} className={className} />;
  if (s.includes('starts here')) return <StartsHereIcon size={size} className={className} />;
  return                                <InfoIcon        size={size} className={className} />;
};

/**
 * Return the operator logo component for a two-letter TransportAPI TOC code.
 * Falls back to the generic `OperatorIcon` for unrecognised codes.
 *
 * @param {string} operatorCode  - Two-letter TOC code (e.g. `'GW'`, `'XC'`).
 * @param {string} [size='1em']        - CSS font-size / height passed to the logo.
 * @param {string} [className='']      - Additional CSS class for the logo.
 * @returns {JSX.Element}
 */
export const getOperatorIcon = (operatorCode, size = '1em', className = '') => {
  switch (operatorCode) {
    case 'GW': return <GreatWesternLogo    size={size} className={className} />;
    case 'SW': return <SouthWesternLogo    size={size} className={className} />;
    case 'XC': return <CrossCountryLogo    size={size} className={className} />;
    case 'HX': return <HeathrowExpressLogo size={size} className={className} />;
    case 'GX': return <GatwickExpressLogo  size={size} className={className} />;
    case 'XR': return <TfLLogo             size={size} className={className} />;
    case 'SN': return <SouthernLogo        size={size} className={className} />;
    case 'SE': return <SouthEasternLogo    size={size} className={className} />;
    case 'LM': return <WestMidlandsLogo    size={size} className={className} />;
    case 'VT': return <AvantiWCLogo        size={size} className={className} />;
    case 'LO': return <OvergroundLogo      size={size} className={className} />;
    case 'GR': return <LNERLogo            size={size} className={className} />;
    case 'GN': return <GreatNorthernLogo   size={size} className={className} />;
    case 'GC': return <GrandCentralLogo    size={size} className={className} />;
    case 'LE': return <GreaterAngliaLogo   size={size} className={className} />;
    case 'HT': return <HullTrainsLogo      size={size} className={className} />;
    case 'CH': return <ChilternRailwaysLogo size={size} className={className} />;
    case 'NT': return <NorthernLogo        size={size} className={className} />;
    case 'AW': return <TfWLogo             size={size} className={className} />;
    case 'EM': return <EastMidlandsLogo    size={size} className={className} />;
    case 'TP': return <TransPennineLogo    size={size} className={className} />;
    case 'SR': return <ScotRailLogo        size={size} className={className} />;
    case 'TL': return <ThamesLinkLogo      size={size} className={className} />;
    default:   return <OperatorIcon        size={size} className={className} />;
  }
};

// ---------------------------------------------------------------------------
// Convenience bundle (optional – all items are also named exports above)
// ---------------------------------------------------------------------------

/** All icon components and helpers in a single object for destructured imports. */
export const Icons = {
  BritishRailLogo, GreatWesternLogo, CrossCountryLogo, SouthWesternLogo,
  HeathrowExpressLogo, GatwickExpressLogo, TfLLogo, SouthernLogo,
  SouthEasternLogo, AvantiWCLogo, WestMidlandsLogo, OvergroundLogo,
  LNERLogo, GreatNorthernLogo, GrandCentralLogo, HullTrainsLogo,
  GreaterAngliaLogo, ChilternRailwaysLogo, NorthernLogo, TfWLogo,
  EastMidlandsLogo, TransPennineLogo, ScotRailLogo, ThamesLinkLogo,
  Train: TrainIcon, Station: StationIcon, Clock: ClockIcon,
  Platform: PlatformIcon, Info: InfoIcon, Schedule: ScheduleIcon,
  Warning: WarningIcon, Check: CheckIcon, Location: LocationIcon,
  Route: RouteIcon, Operator: OperatorIcon, ArrowRight: ArrowRightIcon,
  OnTime: OnTimeIcon, Early: EarlyIcon, Late: LateIcon,
  Cancelled: CancelledIcon, StartsHere: StartsHereIcon,
  getStatusIcon, getOperatorIcon,
};

export default Icons;
