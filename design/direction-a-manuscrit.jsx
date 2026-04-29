// Direction A — "Manuscrit"
// Cream paper, EB Garamond editorial serif, sober ink-black accents.
// Feel: an editor's writing desk. Quiet, literary, generous margins.

function ManuscritDirection() {
  const P = window.PROJECT;
  const [active, setActive] = React.useState("overview");
  const [expanded, setExpanded] = React.useState({ 5: true, 6: true });

  const toggle = (n) => setExpanded(e => ({ ...e, [n]: !e[n] }));

  return (
    <div data-screen-label="A · Manuscrit" style={mStyles.root}>
      {/* Top bar — thin, ivory, hairline rule */}
      <header style={mStyles.topbar}>
        <div style={mStyles.topbarInner}>
          <div style={mStyles.brandRow}>
            <div style={mStyles.brandMark}>É</div>
            <div style={mStyles.brand}>Écrivain</div>
            <span style={mStyles.crumbSep}>/</span>
            <span style={mStyles.crumb}>Projets</span>
            <span style={mStyles.crumbSep}>/</span>
            <span style={mStyles.crumbActive}>{P.title}</span>
          </div>
          <div style={mStyles.topbarRight}>
            <div style={mStyles.search}>
              <span style={{ opacity: .5 }}>⌕</span>
              <span style={{ opacity: .6 }}>Rechercher dans le manuscrit</span>
              <kbd style={mStyles.kbd}>⌘K</kbd>
            </div>
            <button style={mStyles.iconBtnTop}>✎</button>
            <button style={mStyles.iconBtnTop}>↗</button>
            <div style={mStyles.avatar}>D</div>
          </div>
        </div>
      </header>

      <div style={mStyles.body}>
        {/* Left rail */}
        <aside style={mStyles.leftRail}>
          <div style={mStyles.railSection}>
            <div style={mStyles.railLabel}>Espace</div>
            {P.workspaceNav.map(n => (
              <a key={n.id} style={mStyles.railLink}>
                <span style={mStyles.railDot} />{n.label}
              </a>
            ))}
          </div>

          <div style={mStyles.bookCard}>
            <div style={mStyles.bookCover}>
              <div style={mStyles.bookCoverInner}>
                <div style={{ fontSize: 9, letterSpacing: '0.2em', opacity: .7 }}>UN ROMAN DE</div>
                <div style={{ fontSize: 10, marginTop: 2, opacity: .8 }}>D. ROUAIX</div>
                <div style={mStyles.bookTitle}>HAINDAL</div>
                <div style={mStyles.bookOrn}>❦</div>
              </div>
            </div>
            <div style={{ marginTop: 10 }}>
              <div style={mStyles.bookName}>{P.title}</div>
              <div style={mStyles.bookMeta}>Roman · en cours</div>
            </div>
          </div>

          <div style={mStyles.railSection}>
            <div style={mStyles.railLabel}>Manuscrit</div>
            {P.sections.map(s => (
              <a
                key={s.id}
                onClick={() => setActive(s.id)}
                style={{
                  ...mStyles.railLink,
                  ...(active === s.id ? mStyles.railLinkActive : null),
                }}
              >
                <span style={mStyles.railDot} />
                <span style={{ flex: 1 }}>{s.label}</span>
                {s.count != null && <span style={mStyles.railCount}>{s.count}</span>}
              </a>
            ))}
          </div>
        </aside>

        {/* Center column */}
        <main style={mStyles.center}>
          <div style={mStyles.pageHeader}>
            <div>
              <div style={mStyles.eyebrow}>Manuscrit · Vue d'ensemble</div>
              <h1 style={mStyles.h1}>Actes &amp; chapitres</h1>
              <div style={mStyles.subtitle}>
                Acte unique · 67 chapitres · 764 pages · dernière modification il y a 12 minutes
              </div>
            </div>
            <div style={mStyles.pageActions}>
              <div style={mStyles.searchInline}>
                <span style={{ opacity: .5 }}>⌕</span>
                <input placeholder="Filtrer un chapitre ou une scène…" style={mStyles.searchInput} />
              </div>
              <button style={mStyles.btnGhost}>Importer</button>
              <button style={mStyles.btnPrimary}>+ Nouveau chapitre</button>
            </div>
          </div>

          {/* Pre-chapters group */}
          <section style={mStyles.group}>
            <header style={mStyles.groupHeader}>
              <span style={mStyles.chev}>›</span>
              <span style={mStyles.groupTitle}>Sections avant les chapitres</span>
              <span style={mStyles.groupBadge}>0</span>
              <span style={{ flex: 1 }} />
              <button style={mStyles.iconBtn} title="Ajouter">+</button>
            </header>
          </section>

          {/* Acts */}
          {P.acts.map(act => (
            <section key={act.id} style={mStyles.group}>
              <header style={{ ...mStyles.groupHeader, ...mStyles.groupHeaderOpen }}>
                <span style={mStyles.chevOpen}>⌄</span>
                <span style={mStyles.groupTitle}>{act.label}</span>
                <span style={{ flex: 1 }} />
                <span style={mStyles.groupMeta}>{act.chapterCount} chapitres · {act.pageCount} p.</span>
              </header>

              <ol style={mStyles.chList}>
                {act.chapters.map(ch => (
                  <li key={ch.n} style={ch.active ? mStyles.chRowActive : mStyles.chRow}>
                    <div style={mStyles.chHead} onClick={() => toggle(ch.n)}>
                      <span style={mStyles.chNum}>Ch. {String(ch.n).padStart(2, '0')}</span>
                      <span style={mStyles.chDot} />
                      <span style={mStyles.chTitle}>{ch.title}</span>
                      <span style={mStyles.chMeta}>· {ch.date}</span>
                      {ch.scenes && (
                        <span style={mStyles.chScenes}>
                          {expanded[ch.n] ? '⌄' : '›'} {ch.scenes}
                        </span>
                      )}
                      <span style={{ flex: 1 }} />
                      <span style={mStyles.chWords}>{ch.words.toLocaleString('fr-FR')} <em style={mStyles.unitEm}>m</em></span>
                      {ch.active && (
                        <div style={mStyles.chActions}>
                          <button style={mStyles.iconBtn} title="Ajouter">+</button>
                          <button style={mStyles.iconBtn} title="Éditer">✎</button>
                          <button style={mStyles.iconBtn} title="Lire">⌖</button>
                          <button style={mStyles.iconBtn} title="Plus">⋯</button>
                        </div>
                      )}
                    </div>
                    {expanded[ch.n] && ch.children && (
                      <ul style={mStyles.scList}>
                        {ch.children.map((s, i) => (
                          <li key={i} style={mStyles.scRow}>
                            <span style={mStyles.scIndent}>—</span>
                            <span style={mStyles.scTitle}>{s.title}</span>
                            <span style={{ flex: 1 }} />
                            <span style={mStyles.chWords}>{s.words.toLocaleString('fr-FR')} <em style={mStyles.unitEm}>m</em></span>
                          </li>
                        ))}
                        <li style={{ ...mStyles.scRow, opacity: .55 }}>
                          <span style={mStyles.scIndent}>＋</span>
                          <span style={mStyles.scTitle}>Ajouter une scène</span>
                        </li>
                      </ul>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          ))}
        </main>

        {/* Right rail */}
        <aside style={mStyles.rightRail}>
          <div style={mStyles.progCard}>
            <div style={mStyles.progLabel}>Progression du manuscrit</div>
            <div style={mStyles.progNum}>
              <span style={mStyles.progBig}>162 080</span>
              <span style={mStyles.progUnit}>mots</span>
            </div>
            <div style={mStyles.progBarTrack}>
              <div style={mStyles.progBarFill} />
            </div>
            <div style={mStyles.progFoot}>
              <div><strong>772</strong> / 650 pages <span style={mStyles.progPct}>· 100%</span></div>
              <div style={{ opacity: .65, marginTop: 4 }}>772 pages écrites</div>
            </div>
          </div>

          <div style={mStyles.todayCard}>
            <div style={mStyles.todayHead}>
              <div style={mStyles.todayLabel}>Aujourd'hui</div>
              <div style={mStyles.todayStreak}>23 j. d'affilée</div>
            </div>
            <div style={mStyles.todayBig}>1 284 <span style={mStyles.todayUnit}>mots</span></div>
            <div style={mStyles.todayGoal}>Objectif 1 000 mots · dépassé de 28%</div>
          </div>

          <div style={mStyles.toolsCard}>
            {P.tools.map(t => (
              <button key={t.id} style={mStyles.toolBtn}>
                <span style={mStyles.toolDot} />
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={mStyles.toolArrow}>›</span>
              </button>
            ))}
          </div>

          <div style={mStyles.toolsCard}>
            {P.meta.map(t => (
              <button key={t.id} style={mStyles.toolBtnSubtle}>
                <span style={mStyles.toolDot} />
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={mStyles.toolArrow}>›</span>
              </button>
            ))}
          </div>

          <div style={mStyles.quote}>
            <div style={mStyles.quoteOrn}>“</div>
            <div style={mStyles.quoteText}>
              On n'écrit pas un livre, on l'élague.
            </div>
            <div style={mStyles.quoteAuthor}>— note du jour</div>
          </div>
        </aside>
      </div>
    </div>
  );
}

const mStyles = {
  root: {
    width: '100%',
    height: '100%',
    background: '#f4efe6',
    color: '#2a2520',
    fontFamily: '"EB Garamond", "Iowan Old Style", Georgia, serif',
    fontSize: 15,
    lineHeight: 1.45,
    overflow: 'hidden',
    display: 'flex',
    flexDirection: 'column',
  },

  topbar: {
    background: '#fbf7ee',
    borderBottom: '1px solid #e3dccd',
    flexShrink: 0,
  },
  topbarInner: {
    height: 56,
    padding: '0 28px',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 24,
  },
  brandRow: { display: 'flex', alignItems: 'center', gap: 12 },
  brandMark: {
    width: 28, height: 28, borderRadius: 4,
    background: '#1a1612', color: '#f4efe6',
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 18, fontWeight: 600,
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontStyle: 'italic',
  },
  brand: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontWeight: 500, fontSize: 19,
    letterSpacing: '0.01em',
  },
  crumbSep: { opacity: .35, fontSize: 14 },
  crumb: { fontSize: 14, opacity: .7 },
  crumbActive: { fontSize: 14, fontWeight: 600, fontStyle: 'italic' },
  topbarRight: { display: 'flex', alignItems: 'center', gap: 12 },
  search: {
    display: 'flex', alignItems: 'center', gap: 8,
    height: 34, padding: '0 12px',
    background: '#f4efe6',
    border: '1px solid #e3dccd',
    borderRadius: 4,
    fontSize: 13, minWidth: 320,
  },
  kbd: {
    marginLeft: 'auto',
    fontSize: 10,
    padding: '2px 5px',
    border: '1px solid #d8cfba',
    borderRadius: 3,
    background: '#fbf7ee',
    fontFamily: 'ui-monospace, monospace',
    opacity: .7,
  },
  iconBtnTop: {
    width: 34, height: 34, border: '1px solid #e3dccd',
    background: '#fbf7ee', borderRadius: 4,
    cursor: 'pointer', fontSize: 14, color: '#3a322a',
  },
  avatar: {
    width: 34, height: 34, borderRadius: '50%',
    background: '#1a1612', color: '#f4efe6',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontSize: 13, fontWeight: 600, fontStyle: 'italic',
  },

  body: { flex: 1, display: 'flex', minHeight: 0 },

  leftRail: {
    width: 248,
    background: '#efe9dc',
    borderRight: '1px solid #e3dccd',
    padding: '20px 16px',
    overflowY: 'auto',
    flexShrink: 0,
  },
  railSection: { marginBottom: 24 },
  railLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#7a6e5a',
    marginBottom: 8, padding: '0 8px',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontWeight: 600,
  },
  railLink: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '6px 8px',
    fontSize: 14,
    color: '#3a322a',
    cursor: 'pointer',
    borderRadius: 3,
    fontFamily: '"EB Garamond", Georgia, serif',
  },
  railLinkActive: {
    background: '#fbf7ee',
    fontStyle: 'italic',
    fontWeight: 600,
    boxShadow: 'inset 2px 0 0 #6b3f2a',
  },
  railDot: {
    width: 4, height: 4, borderRadius: '50%',
    background: 'currentColor', opacity: .35,
  },
  railCount: {
    fontSize: 11, opacity: .55,
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontVariantNumeric: 'tabular-nums',
  },

  bookCard: {
    margin: '4px 0 24px',
    padding: 14,
    background: '#fbf7ee',
    border: '1px solid #e3dccd',
    borderRadius: 4,
  },
  bookCover: {
    aspectRatio: '2 / 3',
    background: 'linear-gradient(155deg, #2a1f1a 0%, #4a382c 100%)',
    border: '1px solid #1a1612',
    boxShadow: '2px 2px 0 #d8cfba',
    padding: 14,
    color: '#e8dcc6',
    display: 'flex', flexDirection: 'column',
    justifyContent: 'center', alignItems: 'center',
    textAlign: 'center',
  },
  bookCoverInner: {
    border: '1px solid rgba(232,220,198,.35)',
    width: '100%', height: '100%',
    padding: '14px 8px',
    display: 'flex', flexDirection: 'column',
    justifyContent: 'space-between', alignItems: 'center',
  },
  bookTitle: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 22, fontWeight: 600,
    letterSpacing: '0.05em',
    fontStyle: 'italic',
  },
  bookOrn: { fontSize: 14, opacity: .7 },
  bookName: { fontWeight: 600, fontSize: 15, fontStyle: 'italic' },
  bookMeta: {
    fontSize: 11, color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    marginTop: 2,
  },

  center: {
    flex: 1,
    overflowY: 'auto',
    padding: '32px 40px 64px',
    background: '#fbf7ee',
  },
  pageHeader: {
    display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end',
    gap: 24, marginBottom: 32,
    paddingBottom: 20, borderBottom: '1px solid #e3dccd',
  },
  eyebrow: {
    fontSize: 11, letterSpacing: '0.22em',
    textTransform: 'uppercase',
    color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontWeight: 600, marginBottom: 6,
  },
  h1: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 38, fontWeight: 500,
    margin: 0, letterSpacing: '-0.01em',
    lineHeight: 1.1,
  },
  subtitle: {
    fontSize: 14, color: '#7a6e5a',
    marginTop: 8, fontStyle: 'italic',
  },
  pageActions: { display: 'flex', alignItems: 'center', gap: 8 },
  searchInline: {
    display: 'flex', alignItems: 'center', gap: 6,
    height: 34, padding: '0 12px',
    background: '#fff', border: '1px solid #e3dccd',
    borderRadius: 3, fontSize: 13, width: 240,
  },
  searchInput: {
    border: 'none', outline: 'none', background: 'transparent',
    flex: 1, fontFamily: 'inherit', fontSize: 13, color: '#2a2520',
  },
  btnGhost: {
    height: 34, padding: '0 14px',
    background: 'transparent', border: '1px solid #d8cfba',
    fontFamily: 'inherit', fontSize: 13,
    cursor: 'pointer', borderRadius: 3, color: '#3a322a',
  },
  btnPrimary: {
    height: 34, padding: '0 16px',
    background: '#1a1612', color: '#f4efe6',
    border: '1px solid #1a1612',
    fontFamily: 'inherit', fontSize: 13, fontStyle: 'italic',
    cursor: 'pointer', borderRadius: 3,
  },

  group: { marginBottom: 18 },
  groupHeader: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '12px 16px',
    background: '#f4efe6',
    border: '1px solid #e3dccd',
    borderRadius: 4,
    cursor: 'pointer',
  },
  groupHeaderOpen: {
    borderRadius: '4px 4px 0 0',
    borderBottom: '1px dashed #d8cfba',
  },
  chev: { fontSize: 14, color: '#7a6e5a', width: 12, textAlign: 'center' },
  chevOpen: { fontSize: 14, color: '#3a322a', width: 12, textAlign: 'center' },
  groupTitle: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 17, fontWeight: 600, fontStyle: 'italic',
  },
  groupBadge: {
    fontSize: 11,
    padding: '1px 8px',
    background: '#fff',
    border: '1px solid #e3dccd',
    borderRadius: 10,
    color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
  },
  groupMeta: {
    fontSize: 12, color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontVariantNumeric: 'tabular-nums',
  },
  iconBtn: {
    width: 26, height: 26,
    background: '#fbf7ee', border: '1px solid #e3dccd',
    borderRadius: 3, cursor: 'pointer',
    fontSize: 12, color: '#3a322a',
  },

  chList: {
    margin: 0, padding: 0, listStyle: 'none',
    background: '#fbf7ee',
    border: '1px solid #e3dccd', borderTop: 'none',
    borderRadius: '0 0 4px 4px',
  },
  chRow: { borderBottom: '1px solid #ece5d4' },
  chRowActive: {
    borderBottom: '1px solid #ece5d4',
    background: '#f4ece0',
    boxShadow: 'inset 3px 0 0 #6b3f2a',
  },
  chHead: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '11px 18px',
    cursor: 'pointer',
  },
  chNum: {
    fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
    fontSize: 11, letterSpacing: '0.04em',
    color: '#7a6e5a',
    minWidth: 52,
  },
  chDot: {
    width: 6, height: 6, borderRadius: '50%',
    background: '#c4a575', opacity: .7,
  },
  chTitle: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 16, fontWeight: 500,
  },
  chMeta: {
    fontSize: 13, color: '#7a6e5a',
    fontStyle: 'italic',
  },
  chScenes: {
    fontSize: 11, color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontVariantNumeric: 'tabular-nums',
    padding: '1px 6px',
    border: '1px solid #e3dccd',
    borderRadius: 10,
    background: '#fff',
  },
  chWords: {
    fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
    fontSize: 12, color: '#3a322a',
    fontVariantNumeric: 'tabular-nums',
  },
  unitEm: { fontStyle: 'normal', opacity: .55, marginLeft: 2 },
  chActions: {
    display: 'flex', gap: 4, marginLeft: 8,
  },

  scList: {
    margin: 0, padding: '4px 0 10px',
    listStyle: 'none',
    background: '#f9f4e8',
    borderTop: '1px solid #ece5d4',
  },
  scRow: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '5px 18px 5px 64px',
    fontSize: 14,
  },
  scIndent: { color: '#a89472', width: 14 },
  scTitle: { fontFamily: '"EB Garamond", Georgia, serif', fontStyle: 'italic' },

  rightRail: {
    width: 280,
    background: '#efe9dc',
    borderLeft: '1px solid #e3dccd',
    padding: 18,
    overflowY: 'auto',
    flexShrink: 0,
    display: 'flex', flexDirection: 'column', gap: 14,
  },
  progCard: {
    padding: 18,
    background: '#fbf7ee',
    border: '1px solid #e3dccd',
    borderRadius: 4,
  },
  progLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#7a6e5a', fontWeight: 600,
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
  },
  progNum: { display: 'flex', alignItems: 'baseline', gap: 6, marginTop: 8 },
  progBig: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 32, fontWeight: 600,
    fontVariantNumeric: 'tabular-nums',
    letterSpacing: '-0.01em',
  },
  progUnit: { fontSize: 13, color: '#7a6e5a', fontStyle: 'italic' },
  progBarTrack: {
    height: 4, marginTop: 14,
    background: '#e3dccd', borderRadius: 2,
    overflow: 'hidden',
  },
  progBarFill: { width: '100%', height: '100%', background: '#6b3f2a' },
  progFoot: {
    fontSize: 12, color: '#3a322a',
    marginTop: 10,
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontVariantNumeric: 'tabular-nums',
  },
  progPct: { color: '#6b3f2a', fontWeight: 600 },

  todayCard: {
    padding: 16,
    background: '#fbf7ee',
    border: '1px solid #e3dccd',
    borderRadius: 4,
  },
  todayHead: { display: 'flex', justifyContent: 'space-between', alignItems: 'center' },
  todayLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase', color: '#7a6e5a',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    fontWeight: 600,
  },
  todayStreak: {
    fontSize: 11, color: '#6b3f2a', fontStyle: 'italic',
  },
  todayBig: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 26, fontWeight: 600,
    marginTop: 6,
    fontVariantNumeric: 'tabular-nums',
  },
  todayUnit: { fontSize: 13, color: '#7a6e5a', fontStyle: 'italic', fontWeight: 400 },
  todayGoal: {
    fontSize: 12, color: '#3a322a', marginTop: 6,
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
  },

  toolsCard: {
    background: '#fbf7ee',
    border: '1px solid #e3dccd',
    borderRadius: 4,
    overflow: 'hidden',
  },
  toolBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '11px 14px',
    background: 'transparent', border: 'none',
    borderBottom: '1px solid #ece5d4',
    cursor: 'pointer',
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 15,
    color: '#2a2520',
  },
  toolBtnSubtle: {
    width: '100%',
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '9px 14px',
    background: 'transparent', border: 'none',
    borderBottom: '1px solid #ece5d4',
    cursor: 'pointer',
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 14,
    color: '#5a4f43',
  },
  toolDot: {
    width: 6, height: 6, borderRadius: '50%',
    background: '#c4a575',
  },
  toolArrow: { color: '#a89472', fontSize: 16 },

  quote: {
    padding: '14px 16px 16px',
    background: 'transparent',
    borderTop: '1px solid #d8cfba',
    color: '#5a4f43',
    textAlign: 'center',
  },
  quoteOrn: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 36, lineHeight: 0.5, marginTop: 14,
    color: '#6b3f2a', fontStyle: 'italic',
  },
  quoteText: {
    fontFamily: '"EB Garamond", Georgia, serif',
    fontSize: 15, fontStyle: 'italic',
    marginTop: 14, lineHeight: 1.4,
  },
  quoteAuthor: {
    fontSize: 11, color: '#a89472',
    fontFamily: 'ui-sans-serif, system-ui, sans-serif',
    marginTop: 8,
    letterSpacing: '0.05em',
  },
};

window.ManuscritDirection = ManuscritDirection;
