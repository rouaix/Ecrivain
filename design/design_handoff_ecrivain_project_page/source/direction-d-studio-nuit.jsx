// Direction D — "Studio nuit"
// Dark slate base, warm amber accent, Source Serif 4 + Inter UI.
// Feel: a late-night writing session — focused, comfortable for long reading.

function StudioNuitDirection() {
  const P = window.PROJECT;
  const [active, setActive] = React.useState("overview");
  const [expanded, setExpanded] = React.useState({ 5: true, 6: true });
  const toggle = (n) => setExpanded(e => ({ ...e, [n]: !e[n] }));

  return (
    <div data-screen-label="D · Studio nuit" style={dStyles.root}>
      <header style={dStyles.topbar}>
        <div style={dStyles.brandRow}>
          <div style={dStyles.brandMark}>É</div>
          <div style={dStyles.brand}>Écrivain</div>
          <span style={dStyles.crumbSep}>·</span>
          <span style={dStyles.crumb}>Projets</span>
          <span style={dStyles.crumbSep}>·</span>
          <span style={dStyles.crumbActive}>{P.title}</span>
        </div>
        <div style={dStyles.topbarRight}>
          <div style={dStyles.search}>
            <span style={{ opacity: .5 }}>⌕</span>
            <span style={{ opacity: .55 }}>Rechercher dans le manuscrit</span>
            <kbd style={dStyles.kbd}>⌘K</kbd>
          </div>
          <button style={dStyles.iconBtnTop}>✎</button>
          <button style={dStyles.iconBtnTop}>↗</button>
          <div style={dStyles.avatar}>D</div>
        </div>
      </header>

      <div style={dStyles.body}>
        <aside style={dStyles.leftRail}>
          <div style={dStyles.railSection}>
            <div style={dStyles.railLabel}>Espace</div>
            {P.workspaceNav.map(n => (
              <a key={n.id} style={dStyles.railLink}>{n.label}</a>
            ))}
          </div>

          <div style={dStyles.railSection}>
            <div style={dStyles.railLabel}>{P.title}</div>
            {P.sections.map(s => (
              <a
                key={s.id}
                onClick={() => setActive(s.id)}
                style={{ ...dStyles.railLink, ...(active === s.id ? dStyles.railLinkActive : null) }}
              >
                <span style={{ flex: 1 }}>{s.label}</span>
                {s.count != null && <span style={dStyles.railCount}>{s.count}</span>}
              </a>
            ))}
          </div>
        </aside>

        <main style={dStyles.center}>
          <div style={dStyles.pageHeader}>
            <div>
              <div style={dStyles.eyebrow}>Vue d'ensemble du manuscrit</div>
              <h1 style={dStyles.h1}>Actes &amp; chapitres</h1>
              <div style={dStyles.subtitle}>
                1 acte · 67 chapitres · 764 pages · dernière session il y a 12 minutes
              </div>
            </div>
            <div style={dStyles.pageActions}>
              <button style={dStyles.btnGhost}>Trier</button>
              <button style={dStyles.btnPrimary}>+ Nouveau chapitre</button>
            </div>
          </div>

          <section style={dStyles.group}>
            <header style={dStyles.groupHeader}>
              <span style={dStyles.chev}>›</span>
              <span style={dStyles.groupTitle}>Sections avant les chapitres</span>
              <span style={dStyles.groupBadge}>0</span>
            </header>
          </section>

          {P.acts.map(act => (
            <section key={act.id} style={dStyles.group}>
              <header style={{ ...dStyles.groupHeader, ...dStyles.groupHeaderOpen }}>
                <span style={dStyles.chevOpen}>⌄</span>
                <span style={dStyles.groupTitle}>{act.label}</span>
                <span style={{ flex: 1 }} />
                <span style={dStyles.groupMeta}>{act.chapterCount} chapitres · {act.pageCount} p.</span>
              </header>

              <ol style={dStyles.chList}>
                {act.chapters.map(ch => (
                  <li key={ch.n} style={ch.active ? dStyles.chRowActive : dStyles.chRow}>
                    <div style={dStyles.chHead} onClick={() => toggle(ch.n)}>
                      <span style={dStyles.chNum}>Ch. {String(ch.n).padStart(2, '0')}</span>
                      <span style={dStyles.chTitle}>{ch.title}</span>
                      <span style={dStyles.chMeta}>· {ch.date}</span>
                      <span style={dStyles.chScenes}>{expanded[ch.n] ? '⌄' : '›'} {ch.scenes}</span>
                      <span style={{ flex: 1 }} />
                      <span style={dStyles.chWords}>{ch.words.toLocaleString('fr-FR')} <em style={dStyles.unitEm}>m</em></span>
                      {ch.active && (
                        <div style={dStyles.chActions}>
                          <button style={dStyles.iconBtn}>✎</button>
                          <button style={dStyles.iconBtn}>⌖</button>
                          <button style={dStyles.iconBtn}>⋯</button>
                        </div>
                      )}
                    </div>
                    {expanded[ch.n] && ch.children && (
                      <ul style={dStyles.scList}>
                        {ch.children.map((s, i) => (
                          <li key={i} style={dStyles.scRow}>
                            <span style={dStyles.scIndent}>—</span>
                            <span style={dStyles.scTitle}>{s.title}</span>
                            <span style={{ flex: 1 }} />
                            <span style={dStyles.chWords}>{s.words.toLocaleString('fr-FR')} <em style={dStyles.unitEm}>m</em></span>
                          </li>
                        ))}
                        <li style={{ ...dStyles.scRow, opacity: .5 }}>
                          <span style={dStyles.scIndent}>＋</span>
                          <span style={dStyles.scTitle}>Ajouter une scène</span>
                        </li>
                      </ul>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          ))}
        </main>

        <aside style={dStyles.rightRail}>
          <div style={dStyles.progCard}>
            <div style={dStyles.progLabel}>Progression</div>
            <div style={dStyles.progNum}>
              <span style={dStyles.progBig}>162 080</span>
              <span style={dStyles.progUnit}>mots</span>
            </div>
            <div style={dStyles.progBarTrack}><div style={dStyles.progBarFill} /></div>
            <div style={dStyles.progFoot}>
              <strong>772</strong> / 650 pages <span style={dStyles.progPct}>· 100%</span>
            </div>
            <div style={{ ...dStyles.progFoot, opacity: .55, marginTop: 4 }}>122 pages au-delà de l'objectif</div>
          </div>

          <div style={dStyles.todayCard}>
            <div style={dStyles.todayLabel}>Aujourd'hui · 23 j. d'affilée</div>
            <div style={dStyles.todayBig}>1 284 <span style={dStyles.todayUnit}>mots</span></div>
            <div style={dStyles.todayBar}><div style={dStyles.todayBarFill} /></div>
            <div style={dStyles.todayGoal}>Objectif 1 000 dépassé</div>
          </div>

          <div style={dStyles.toolsCard}>
            {P.tools.map(t => (
              <button key={t.id} style={dStyles.toolBtn}>
                <span style={dStyles.toolDot} />
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={dStyles.toolArrow}>›</span>
              </button>
            ))}
          </div>

          <div style={dStyles.toolsCard}>
            {P.meta.map(t => (
              <button key={t.id} style={dStyles.metaBtn}>
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={dStyles.toolArrow}>›</span>
              </button>
            ))}
          </div>
        </aside>
      </div>
    </div>
  );
}

const dStyles = {
  root: {
    width: '100%', height: '100%',
    background: '#161413',
    color: '#e8e2d4',
    fontFamily: 'Inter, "Helvetica Neue", system-ui, sans-serif',
    fontSize: 13.5, lineHeight: 1.45,
    overflow: 'hidden',
    display: 'flex', flexDirection: 'column',
  },

  topbar: {
    height: 52, flexShrink: 0,
    background: '#1c1a18',
    borderBottom: '1px solid #2a2624',
    display: 'flex', alignItems: 'center',
    padding: '0 20px', gap: 16,
  },
  brandRow: { display: 'flex', alignItems: 'center', gap: 10, flex: 1 },
  brandMark: {
    width: 26, height: 26, borderRadius: 4,
    background: '#d4a04a', color: '#1c1a18',
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 16, fontStyle: 'italic', fontWeight: 700,
    display: 'flex', alignItems: 'center', justifyContent: 'center',
  },
  brand: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 17, fontWeight: 500, fontStyle: 'italic',
    color: '#f4ecd8',
  },
  crumbSep: { color: '#5a514a' },
  crumb: { fontSize: 13, color: '#a89c87' },
  crumbActive: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 15, fontWeight: 600, fontStyle: 'italic', color: '#f4ecd8',
  },
  topbarRight: { display: 'flex', alignItems: 'center', gap: 10 },
  search: {
    display: 'flex', alignItems: 'center', gap: 8,
    height: 32, padding: '0 12px',
    background: '#0e0d0c', border: '1px solid #2a2624',
    borderRadius: 5, fontSize: 12.5, minWidth: 280,
    color: '#a89c87',
  },
  kbd: {
    marginLeft: 'auto',
    fontSize: 10, padding: '2px 5px',
    border: '1px solid #2a2624', borderRadius: 3,
    background: '#1c1a18', color: '#a89c87',
    fontFamily: 'ui-monospace, monospace',
  },
  iconBtnTop: {
    width: 32, height: 32, border: '1px solid #2a2624',
    background: '#0e0d0c', borderRadius: 5,
    cursor: 'pointer', fontSize: 13, color: '#e8e2d4',
  },
  avatar: {
    width: 32, height: 32, borderRadius: '50%',
    background: '#d4a04a', color: '#1c1a18',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontSize: 13, fontWeight: 700,
    fontFamily: '"Source Serif 4", Georgia, serif', fontStyle: 'italic',
  },

  body: { flex: 1, display: 'flex', minHeight: 0 },

  leftRail: {
    width: 230, flexShrink: 0,
    background: '#1c1a18',
    borderRight: '1px solid #2a2624',
    padding: '16px 10px',
    overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 4,
  },
  railSection: { display: 'flex', flexDirection: 'column', gap: 1, marginBottom: 16 },
  railLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#5a514a', fontWeight: 600,
    padding: '4px 10px', marginBottom: 4,
    fontFamily: '"Source Serif 4", Georgia, serif', fontStyle: 'italic',
  },
  railLink: {
    display: 'flex', alignItems: 'center',
    padding: '6px 10px',
    fontSize: 13.5, color: '#a89c87',
    cursor: 'pointer', borderRadius: 4,
    fontFamily: '"Source Serif 4", Georgia, serif',
  },
  railLinkActive: {
    background: '#2a2624', color: '#f4ecd8',
    fontWeight: 600, fontStyle: 'italic',
    boxShadow: 'inset 2px 0 0 #d4a04a',
  },
  railCount: {
    fontSize: 11, color: '#5a514a',
    fontVariantNumeric: 'tabular-nums',
    fontFamily: 'Inter, sans-serif',
  },

  center: {
    flex: 1, overflowY: 'auto',
    padding: '28px 36px 56px',
    background: '#161413',
  },
  pageHeader: {
    display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end',
    gap: 24, marginBottom: 28,
    paddingBottom: 18, borderBottom: '1px solid #2a2624',
  },
  eyebrow: {
    fontSize: 11, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#d4a04a', fontWeight: 600,
    marginBottom: 6,
  },
  h1: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 34, fontWeight: 500, fontStyle: 'italic',
    margin: 0, color: '#f4ecd8',
    letterSpacing: '-0.01em',
  },
  subtitle: {
    fontSize: 13, color: '#a89c87',
    fontStyle: 'italic', marginTop: 6,
    fontFamily: '"Source Serif 4", Georgia, serif',
  },
  pageActions: { display: 'flex', alignItems: 'center', gap: 8 },
  btnGhost: {
    height: 32, padding: '0 14px',
    background: 'transparent', border: '1px solid #2a2624',
    borderRadius: 5, fontSize: 13, fontFamily: 'inherit',
    cursor: 'pointer', color: '#e8e2d4',
  },
  btnPrimary: {
    height: 32, padding: '0 14px',
    background: '#d4a04a', color: '#1c1a18', border: 'none',
    borderRadius: 5, fontSize: 13, fontWeight: 600,
    cursor: 'pointer', fontFamily: 'inherit',
  },

  group: {
    marginBottom: 14,
    background: '#1c1a18',
    border: '1px solid #2a2624',
    borderRadius: 6, overflow: 'hidden',
  },
  groupHeader: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '12px 18px', cursor: 'pointer',
  },
  groupHeaderOpen: { borderBottom: '1px solid #2a2624' },
  chev: { fontSize: 14, color: '#5a514a', width: 12 },
  chevOpen: { fontSize: 14, color: '#d4a04a', width: 12 },
  groupTitle: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 17, fontWeight: 600, fontStyle: 'italic',
    color: '#f4ecd8',
  },
  groupBadge: {
    fontSize: 11, padding: '1px 8px',
    background: '#0e0d0c', color: '#a89c87',
    borderRadius: 10, fontVariantNumeric: 'tabular-nums',
  },
  groupMeta: {
    fontSize: 12, color: '#a89c87',
    fontVariantNumeric: 'tabular-nums',
  },

  chList: { margin: 0, padding: 0, listStyle: 'none' },
  chRow: { borderBottom: '1px solid #2a2624' },
  chRowActive: {
    borderBottom: '1px solid #2a2624',
    background: 'linear-gradient(90deg, rgba(212,160,74,0.08), rgba(212,160,74,0))',
    boxShadow: 'inset 3px 0 0 #d4a04a',
  },
  chHead: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '12px 18px', cursor: 'pointer',
  },
  chNum: {
    fontFamily: 'ui-monospace, monospace',
    fontSize: 11, color: '#7a6f5e',
    fontVariantNumeric: 'tabular-nums',
    minWidth: 52,
  },
  chTitle: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 16, fontWeight: 500,
    color: '#f4ecd8',
  },
  chMeta: { fontSize: 13, color: '#a89c87', fontStyle: 'italic',
    fontFamily: '"Source Serif 4", Georgia, serif' },
  chScenes: {
    fontSize: 11, color: '#a89c87',
    fontVariantNumeric: 'tabular-nums',
    padding: '1px 7px',
    border: '1px solid #2a2624',
    borderRadius: 10,
    background: '#0e0d0c',
  },
  chWords: {
    fontFamily: 'ui-monospace, monospace',
    fontSize: 12, color: '#e8e2d4',
    fontVariantNumeric: 'tabular-nums',
  },
  unitEm: { fontStyle: 'normal', opacity: .55, marginLeft: 2 },
  chActions: { display: 'flex', gap: 4, marginLeft: 8 },
  iconBtn: {
    width: 26, height: 26,
    background: '#0e0d0c', border: '1px solid #2a2624',
    borderRadius: 4, cursor: 'pointer',
    fontSize: 11, color: '#e8e2d4',
  },

  scList: {
    margin: 0, padding: '4px 0 10px',
    listStyle: 'none',
    background: '#161413',
    borderTop: '1px dashed #2a2624',
  },
  scRow: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '5px 18px 5px 64px',
    fontSize: 13.5,
  },
  scIndent: { color: '#5a514a', width: 14 },
  scTitle: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontStyle: 'italic', color: '#d8cfb8',
  },

  rightRail: {
    width: 280, flexShrink: 0,
    background: '#1c1a18',
    borderLeft: '1px solid #2a2624',
    padding: 14, overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 12,
  },
  progCard: {
    padding: 16,
    background: '#0e0d0c',
    border: '1px solid #2a2624',
    borderRadius: 6,
  },
  progLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#d4a04a', fontWeight: 600,
  },
  progNum: { display: 'flex', alignItems: 'baseline', gap: 6, marginTop: 8 },
  progBig: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 30, fontWeight: 600, fontStyle: 'italic',
    fontVariantNumeric: 'tabular-nums',
    color: '#f4ecd8', letterSpacing: '-0.01em',
  },
  progUnit: { fontSize: 13, color: '#a89c87' },
  progBarTrack: {
    height: 4, marginTop: 12,
    background: '#2a2624', borderRadius: 2, overflow: 'hidden',
  },
  progBarFill: { width: '100%', height: '100%', background: '#d4a04a' },
  progFoot: {
    fontSize: 12, color: '#a89c87',
    marginTop: 10, fontVariantNumeric: 'tabular-nums',
  },
  progPct: { color: '#d4a04a', fontWeight: 600 },

  todayCard: {
    padding: 14, background: '#0e0d0c',
    border: '1px solid #2a2624', borderRadius: 6,
  },
  todayLabel: {
    fontSize: 11, color: '#a89c87',
    fontStyle: 'italic',
    fontFamily: '"Source Serif 4", Georgia, serif',
  },
  todayBig: {
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 26, fontWeight: 600, fontStyle: 'italic',
    fontVariantNumeric: 'tabular-nums',
    color: '#f4ecd8', marginTop: 4,
  },
  todayUnit: { fontSize: 13, color: '#a89c87', fontWeight: 400, fontStyle: 'normal' },
  todayBar: {
    height: 3, marginTop: 10,
    background: '#2a2624', borderRadius: 2, overflow: 'hidden',
  },
  todayBarFill: { width: '100%', height: '100%', background: '#d4a04a' },
  todayGoal: { fontSize: 11.5, color: '#a89c87', marginTop: 6 },

  toolsCard: {
    background: '#0e0d0c',
    border: '1px solid #2a2624',
    borderRadius: 6, overflow: 'hidden',
  },
  toolBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '10px 14px',
    background: 'transparent', border: 'none',
    borderBottom: '1px solid #2a2624',
    cursor: 'pointer',
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 14, color: '#e8e2d4',
  },
  toolDot: { width: 5, height: 5, borderRadius: '50%', background: '#d4a04a' },
  toolArrow: { color: '#5a514a', fontSize: 16 },
  metaBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '8px 14px',
    background: 'transparent', border: 'none',
    borderBottom: '1px solid #2a2624',
    cursor: 'pointer',
    fontFamily: '"Source Serif 4", Georgia, serif',
    fontSize: 13, color: '#a89c87',
  },
};

window.StudioNuitDirection = StudioNuitDirection;
