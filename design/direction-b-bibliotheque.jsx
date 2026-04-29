// Direction B — "Bibliothèque moderne"
// Off-white + deep ink-blue accents. Inter for UI, Lora for titles.
// Feel: a polished modern writing tool — Linear-meets-Notion but literary.

function BibliothequeDirection() {
  const P = window.PROJECT;
  const [active, setActive] = React.useState("overview");
  const [expanded, setExpanded] = React.useState({ 5: true, 6: true });
  const toggle = (n) => setExpanded(e => ({ ...e, [n]: !e[n] }));

  return (
    <div data-screen-label="B · Bibliothèque" style={bStyles.root}>
      <header style={bStyles.topbar}>
        <div style={bStyles.brandRow}>
          <div style={bStyles.brandMark}>É</div>
          <div style={bStyles.brand}>Écrivain</div>
        </div>
        <div style={bStyles.crumbRow}>
          <span style={bStyles.crumb}>Projets</span>
          <span style={bStyles.crumbSep}>›</span>
          <span style={bStyles.crumbActive}>{P.title}</span>
          <span style={bStyles.statusPill}>● En cours</span>
        </div>
        <div style={bStyles.topbarRight}>
          <button style={bStyles.topBtn}>
            <span style={{ opacity: .55 }}>⌕</span> Rechercher
            <kbd style={bStyles.kbd}>⌘K</kbd>
          </button>
          <button style={bStyles.topBtn}>Partager</button>
          <button style={bStyles.topBtnPrimary}>Reprendre l'écriture →</button>
          <div style={bStyles.avatar}>D</div>
        </div>
      </header>

      <div style={bStyles.body}>
        <aside style={bStyles.leftRail}>
          <div style={bStyles.workspaceSwitch}>
            <div style={bStyles.workspaceMark}>DR</div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={bStyles.workspaceName}>Daniel Rouaix</div>
              <div style={bStyles.workspaceMeta}>Espace personnel</div>
            </div>
            <span style={{ color: '#94a0b8' }}>⌄</span>
          </div>

          <div style={bStyles.railSection}>
            {P.workspaceNav.map(n => (
              <a key={n.id} style={bStyles.railLink}>
                <span style={bStyles.railIcon}>{iconFor(n.icon)}</span>
                {n.label}
              </a>
            ))}
          </div>

          <div style={bStyles.railLabel}>Manuscrit en cours</div>
          <div style={bStyles.projectChip}>
            <div style={bStyles.projectSpine} />
            <div style={{ flex: 1 }}>
              <div style={bStyles.projectName}>{P.title}</div>
              <div style={bStyles.projectMeta}>Roman · 162 080 mots</div>
            </div>
          </div>

          <div style={bStyles.railSection}>
            {P.sections.map(s => (
              <a
                key={s.id}
                onClick={() => setActive(s.id)}
                style={{
                  ...bStyles.railLink,
                  ...(active === s.id ? bStyles.railLinkActive : null),
                }}
              >
                <span style={bStyles.bullet}>—</span>
                <span style={{ flex: 1 }}>{s.label}</span>
                {s.count != null && <span style={bStyles.railCount}>{s.count}</span>}
              </a>
            ))}
          </div>
        </aside>

        <main style={bStyles.center}>
          <div style={bStyles.pageHeader}>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={bStyles.eyebrow}>HAINDAL · Vue d'ensemble</div>
              <h1 style={bStyles.h1}>Actes &amp; chapitres</h1>
              <div style={bStyles.statRow}>
                <Stat label="Mots" value="162 080" trend="+1 284 aujourd'hui" />
                <Stat label="Pages" value="772" trend="objectif 650 atteint" tone="ok" />
                <Stat label="Chapitres" value="67" trend="dans 1 acte" />
                <Stat label="Série" value="23 j." trend="en cours" tone="ok" />
              </div>
            </div>
          </div>

          <div style={bStyles.toolbar}>
            <div style={bStyles.tabs}>
              <button style={bStyles.tabActive}>Structure</button>
              <button style={bStyles.tab}>Manuscrit</button>
              <button style={bStyles.tab}>Personnages</button>
              <button style={bStyles.tab}>Notes</button>
              <button style={bStyles.tab}>Historique</button>
            </div>
            <div style={bStyles.toolbarRight}>
              <div style={bStyles.searchInline}>
                <span style={{ opacity: .55 }}>⌕</span>
                <input placeholder="Filtrer chapitres et scènes…" style={bStyles.searchInput} />
              </div>
              <button style={bStyles.btnGhost}>Trier</button>
              <button style={bStyles.btnPrimary}>+ Nouveau chapitre</button>
            </div>
          </div>

          {/* Pre-chapters */}
          <section style={bStyles.group}>
            <header style={bStyles.groupHeader}>
              <span style={bStyles.chev}>›</span>
              <span style={bStyles.groupTitle}>Sections avant les chapitres</span>
              <span style={bStyles.groupCount}>0</span>
              <span style={{ flex: 1 }} />
              <button style={bStyles.iconBtn}>+</button>
            </header>
          </section>

          {P.acts.map(act => (
            <section key={act.id} style={bStyles.group}>
              <header style={{ ...bStyles.groupHeader, ...bStyles.groupHeaderOpen }}>
                <span style={bStyles.chevOpen}>⌄</span>
                <span style={bStyles.groupTitle}>{act.label}</span>
                <span style={{ flex: 1 }} />
                <span style={bStyles.groupMeta}>{act.chapterCount} chapitres · {act.pageCount} pages</span>
              </header>

              <div style={bStyles.tableHead}>
                <div style={{ width: 50 }}>#</div>
                <div style={{ flex: 1 }}>Titre</div>
                <div style={{ width: 130 }}>Date</div>
                <div style={{ width: 80, textAlign: 'right' }}>Scènes</div>
                <div style={{ width: 100, textAlign: 'right' }}>Mots</div>
                <div style={{ width: 110 }} />
              </div>

              <ol style={bStyles.chList}>
                {act.chapters.map(ch => (
                  <li key={ch.n} style={ch.active ? bStyles.chRowActive : bStyles.chRow}>
                    <div style={bStyles.chHead} onClick={() => toggle(ch.n)}>
                      <div style={{ width: 50 }}>
                        <span style={bStyles.chNum}>{String(ch.n).padStart(2, '0')}</span>
                      </div>
                      <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
                        <span style={bStyles.chTitle}>{ch.title}</span>
                        {ch.active && <span style={bStyles.editingPill}>en cours</span>}
                      </div>
                      <div style={{ width: 130, fontSize: 13, color: '#5a6781' }}>{ch.date}</div>
                      <div style={{ width: 80, textAlign: 'right' }}>
                        <span style={bStyles.scenePill}>{expanded[ch.n] ? '⌄' : '›'} {ch.scenes}</span>
                      </div>
                      <div style={{ width: 100, textAlign: 'right' }}>
                        <span style={bStyles.chWords}>{ch.words.toLocaleString('fr-FR')}</span>
                      </div>
                      <div style={{ width: 110, display: 'flex', justifyContent: 'flex-end', gap: 4 }}>
                        {ch.active ? (
                          <>
                            <button style={bStyles.iconBtn}>✎</button>
                            <button style={bStyles.iconBtn}>⌖</button>
                            <button style={bStyles.iconBtn}>⋯</button>
                          </>
                        ) : (
                          <button style={bStyles.iconBtnGhost}>⋯</button>
                        )}
                      </div>
                    </div>
                    {expanded[ch.n] && ch.children && (
                      <ul style={bStyles.scList}>
                        {ch.children.map((s, i) => (
                          <li key={i} style={bStyles.scRow}>
                            <div style={{ width: 50 }} />
                            <div style={{ flex: 1, display: 'flex', alignItems: 'center', gap: 8 }}>
                              <span style={bStyles.scTick}>—</span>
                              <span style={bStyles.scTitle}>{s.title}</span>
                            </div>
                            <div style={{ width: 130 }} />
                            <div style={{ width: 80 }} />
                            <div style={{ width: 100, textAlign: 'right' }}>
                              <span style={bStyles.scWords}>{s.words.toLocaleString('fr-FR')}</span>
                            </div>
                            <div style={{ width: 110 }} />
                          </li>
                        ))}
                        <li style={{ ...bStyles.scRow, color: '#94a0b8' }}>
                          <div style={{ width: 50 }} />
                          <div style={{ flex: 1 }}>＋ Ajouter une scène</div>
                          <div style={{ width: 320 }} />
                        </li>
                      </ul>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          ))}
        </main>

        <aside style={bStyles.rightRail}>
          <div style={bStyles.progCard}>
            <div style={bStyles.cardLabel}>Progression</div>
            <div style={bStyles.progNum}>
              <span style={bStyles.progBig}>162 080</span>
              <span style={bStyles.progUnit}>mots</span>
            </div>
            <div style={bStyles.progSub}>
              <strong style={{ color: '#0f172a' }}>772</strong> / 650 pages
              <span style={bStyles.progPct}>· 100%</span>
            </div>
            <div style={bStyles.progBarTrack}>
              <div style={bStyles.progBarFill} />
            </div>
            <div style={bStyles.progFoot}>Objectif initial dépassé · 122 pages au-delà</div>
          </div>

          <div style={bStyles.cardSection}>
            <div style={bStyles.cardLabel}>Outils</div>
            {P.tools.map(t => (
              <button key={t.id} style={bStyles.toolBtn}>
                <span style={bStyles.toolBullet} />
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={bStyles.toolArrow}>→</span>
              </button>
            ))}
          </div>

          <div style={bStyles.cardSection}>
            <div style={bStyles.cardLabel}>Activité récente</div>
            {P.recent.map((r, i) => (
              <div key={i} style={bStyles.activityRow}>
                <div style={bStyles.activityAv}>{r.who[0]}</div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={bStyles.activityLine}>
                    <strong>{r.who}</strong> {r.what.toLowerCase()} <em style={bStyles.activityWhere}>{r.where}</em>
                  </div>
                  <div style={bStyles.activityWhen}>{r.when}</div>
                </div>
              </div>
            ))}
          </div>

          <div style={bStyles.cardSection}>
            <div style={bStyles.cardLabel}>Espace</div>
            {P.meta.map(t => (
              <button key={t.id} style={bStyles.metaBtn}>
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={bStyles.toolArrow}>→</span>
              </button>
            ))}
          </div>
        </aside>
      </div>
    </div>
  );
}

function Stat({ label, value, trend, tone }) {
  return (
    <div style={bStyles.stat}>
      <div style={bStyles.statLabel}>{label}</div>
      <div style={bStyles.statValue}>{value}</div>
      <div style={{
        ...bStyles.statTrend,
        color: tone === 'ok' ? '#0a6e3a' : '#5a6781',
      }}>{trend}</div>
    </div>
  );
}

function iconFor(name) {
  const m = { home: '⌂', chart: '◫', share: '↗', template: '▤', users: '◉' };
  return m[name] || '·';
}

const bStyles = {
  root: {
    width: '100%', height: '100%',
    background: '#f7f6f2',
    color: '#0f172a',
    fontFamily: 'Inter, "Helvetica Neue", system-ui, sans-serif',
    fontSize: 13.5, lineHeight: 1.45,
    overflow: 'hidden',
    display: 'flex', flexDirection: 'column',
  },

  topbar: {
    height: 52, flexShrink: 0,
    background: '#fff',
    borderBottom: '1px solid #e7e3d8',
    display: 'flex', alignItems: 'center',
    padding: '0 18px', gap: 18,
  },
  brandRow: { display: 'flex', alignItems: 'center', gap: 9 },
  brandMark: {
    width: 26, height: 26, borderRadius: 5,
    background: '#1e2a4a', color: '#f7f6f2',
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 16, fontStyle: 'italic',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontWeight: 600,
  },
  brand: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 17, fontWeight: 600,
    letterSpacing: '-0.01em',
  },
  crumbRow: { display: 'flex', alignItems: 'center', gap: 8, flex: 1 },
  crumb: { fontSize: 13, color: '#5a6781' },
  crumbSep: { color: '#cbd0db' },
  crumbActive: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 15, fontWeight: 600, fontStyle: 'italic',
  },
  statusPill: {
    fontSize: 11, padding: '2px 8px',
    background: '#e8f0e6', color: '#0a6e3a',
    borderRadius: 10, marginLeft: 6,
    fontWeight: 500,
  },
  topbarRight: { display: 'flex', alignItems: 'center', gap: 8 },
  topBtn: {
    height: 32, padding: '0 12px',
    background: '#f7f6f2', border: '1px solid #e7e3d8',
    borderRadius: 6, fontSize: 13, color: '#0f172a',
    cursor: 'pointer', fontFamily: 'inherit',
    display: 'flex', alignItems: 'center', gap: 8,
  },
  kbd: {
    fontSize: 10, padding: '2px 5px',
    border: '1px solid #cbd0db', borderRadius: 3,
    background: '#fff', color: '#5a6781',
    fontFamily: 'ui-monospace, monospace',
  },
  topBtnPrimary: {
    height: 32, padding: '0 14px',
    background: '#1e2a4a', color: '#fff', border: 'none',
    borderRadius: 6, fontSize: 13, fontWeight: 500,
    cursor: 'pointer', fontFamily: 'inherit',
  },
  avatar: {
    width: 32, height: 32, borderRadius: '50%',
    background: '#1e2a4a', color: '#f7f6f2',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontSize: 13, fontWeight: 600,
    fontFamily: 'Lora, Georgia, serif', fontStyle: 'italic',
  },

  body: { flex: 1, display: 'flex', minHeight: 0 },

  leftRail: {
    width: 240, flexShrink: 0,
    background: '#fff',
    borderRight: '1px solid #e7e3d8',
    padding: '12px 12px 18px',
    overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 4,
  },
  workspaceSwitch: {
    display: 'flex', alignItems: 'center', gap: 9,
    padding: 8, background: '#f7f6f2',
    border: '1px solid #e7e3d8',
    borderRadius: 7, marginBottom: 10,
  },
  workspaceMark: {
    width: 28, height: 28, borderRadius: 6,
    background: '#1e2a4a', color: '#f7f6f2',
    fontSize: 11, fontWeight: 600,
    display: 'flex', alignItems: 'center', justifyContent: 'center',
  },
  workspaceName: { fontSize: 13, fontWeight: 600 },
  workspaceMeta: { fontSize: 11, color: '#5a6781' },

  railSection: { display: 'flex', flexDirection: 'column', gap: 1, marginBottom: 14 },
  railLabel: {
    fontSize: 10, letterSpacing: '0.12em',
    textTransform: 'uppercase',
    color: '#94a0b8', fontWeight: 600,
    padding: '8px 10px 4px',
  },
  railLink: {
    display: 'flex', alignItems: 'center', gap: 9,
    padding: '6px 10px',
    fontSize: 13, color: '#0f172a',
    cursor: 'pointer', borderRadius: 5,
  },
  railLinkActive: {
    background: '#eef0f6',
    color: '#1e2a4a', fontWeight: 600,
  },
  railIcon: {
    width: 16, color: '#94a0b8',
    fontSize: 13, textAlign: 'center',
  },
  bullet: { color: '#cbd0db', fontSize: 11, width: 16, textAlign: 'center' },
  railCount: {
    fontSize: 11, color: '#94a0b8',
    fontVariantNumeric: 'tabular-nums',
    background: '#f7f6f2', padding: '0 6px',
    borderRadius: 8,
  },

  projectChip: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: 10, marginBottom: 8,
    background: '#fbfaf6',
    border: '1px solid #e7e3d8',
    borderRadius: 6,
  },
  projectSpine: {
    width: 22, height: 32, borderRadius: 2,
    background: 'linear-gradient(170deg, #2a1f1a, #4a382c)',
    boxShadow: 'inset -2px 0 0 rgba(255,255,255,0.08)',
  },
  projectName: { fontFamily: 'Lora, Georgia, serif', fontSize: 14, fontWeight: 600, fontStyle: 'italic' },
  projectMeta: { fontSize: 11, color: '#5a6781' },

  center: {
    flex: 1,
    overflowY: 'auto',
    padding: '24px 32px 48px',
    background: '#f7f6f2',
  },
  pageHeader: { marginBottom: 22 },
  eyebrow: {
    fontSize: 11, letterSpacing: '0.14em',
    textTransform: 'uppercase',
    color: '#94a0b8', fontWeight: 600,
    marginBottom: 6,
  },
  h1: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 32, fontWeight: 600,
    margin: 0, letterSpacing: '-0.015em',
    fontStyle: 'italic',
  },

  statRow: { display: 'flex', gap: 0, marginTop: 18, background: '#fff', border: '1px solid #e7e3d8', borderRadius: 8, overflow: 'hidden' },
  stat: { flex: 1, padding: '14px 18px', borderRight: '1px solid #e7e3d8' },
  statLabel: {
    fontSize: 10, letterSpacing: '0.12em',
    textTransform: 'uppercase',
    color: '#94a0b8', fontWeight: 600,
  },
  statValue: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 22, fontWeight: 600,
    fontVariantNumeric: 'tabular-nums',
    marginTop: 4, letterSpacing: '-0.01em',
  },
  statTrend: { fontSize: 11.5, marginTop: 2 },

  toolbar: {
    display: 'flex', justifyContent: 'space-between', alignItems: 'center',
    marginBottom: 18, gap: 12,
    paddingBottom: 12, borderBottom: '1px solid #e7e3d8',
  },
  tabs: { display: 'flex', gap: 4 },
  tab: {
    padding: '6px 12px', fontSize: 13,
    background: 'transparent', border: 'none',
    borderRadius: 5, cursor: 'pointer',
    color: '#5a6781', fontFamily: 'inherit',
  },
  tabActive: {
    padding: '6px 12px', fontSize: 13,
    background: '#1e2a4a', color: '#fff',
    border: 'none', borderRadius: 5,
    cursor: 'pointer', fontFamily: 'inherit', fontWeight: 500,
  },
  toolbarRight: { display: 'flex', alignItems: 'center', gap: 8 },
  searchInline: {
    display: 'flex', alignItems: 'center', gap: 6,
    height: 30, padding: '0 10px',
    background: '#fff', border: '1px solid #e7e3d8',
    borderRadius: 6, fontSize: 13, width: 220,
  },
  searchInput: {
    border: 'none', outline: 'none', background: 'transparent',
    flex: 1, fontFamily: 'inherit', fontSize: 13, color: '#0f172a',
  },
  btnGhost: {
    height: 30, padding: '0 12px',
    background: '#fff', border: '1px solid #e7e3d8',
    borderRadius: 6, fontSize: 13, fontFamily: 'inherit',
    cursor: 'pointer', color: '#0f172a',
  },
  btnPrimary: {
    height: 30, padding: '0 14px',
    background: '#1e2a4a', color: '#fff', border: 'none',
    borderRadius: 6, fontSize: 13, fontWeight: 500,
    cursor: 'pointer', fontFamily: 'inherit',
  },

  group: { marginBottom: 14, background: '#fff', border: '1px solid #e7e3d8', borderRadius: 8, overflow: 'hidden' },
  groupHeader: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '11px 16px', cursor: 'pointer',
  },
  groupHeaderOpen: { borderBottom: '1px solid #f0ede4' },
  chev: { fontSize: 13, color: '#94a0b8', width: 12 },
  chevOpen: { fontSize: 13, color: '#0f172a', width: 12 },
  groupTitle: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 16, fontWeight: 600, fontStyle: 'italic',
  },
  groupCount: {
    fontSize: 11, padding: '1px 7px',
    background: '#f7f6f2', borderRadius: 9,
    color: '#5a6781', fontVariantNumeric: 'tabular-nums',
  },
  groupMeta: { fontSize: 12, color: '#5a6781', fontVariantNumeric: 'tabular-nums' },

  tableHead: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '8px 16px',
    fontSize: 10, letterSpacing: '0.1em',
    textTransform: 'uppercase',
    color: '#94a0b8', fontWeight: 600,
    background: '#fbfaf6', borderBottom: '1px solid #f0ede4',
  },

  chList: { margin: 0, padding: 0, listStyle: 'none' },
  chRow: { borderBottom: '1px solid #f0ede4' },
  chRowActive: {
    borderBottom: '1px solid #f0ede4',
    background: '#fbfaf6',
    boxShadow: 'inset 3px 0 0 #1e2a4a',
  },
  chHead: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '11px 16px', cursor: 'pointer',
  },
  chNum: {
    fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
    fontSize: 11, color: '#94a0b8',
    fontVariantNumeric: 'tabular-nums',
  },
  chTitle: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 15, fontWeight: 500,
    overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
  },
  editingPill: {
    fontSize: 10, padding: '1px 6px',
    background: '#eef0f6', color: '#1e2a4a',
    borderRadius: 9, fontWeight: 500,
  },
  scenePill: {
    fontSize: 11, padding: '1px 7px',
    background: '#fbfaf6', border: '1px solid #e7e3d8',
    borderRadius: 9, color: '#5a6781',
    fontVariantNumeric: 'tabular-nums',
  },
  chWords: {
    fontFamily: 'ui-monospace, "SF Mono", Menlo, monospace',
    fontSize: 12, color: '#0f172a',
    fontVariantNumeric: 'tabular-nums',
  },
  iconBtn: {
    width: 26, height: 26,
    background: '#fff', border: '1px solid #e7e3d8',
    borderRadius: 5, cursor: 'pointer',
    fontSize: 12, color: '#0f172a',
  },
  iconBtnGhost: {
    width: 26, height: 26,
    background: 'transparent', border: 'none',
    borderRadius: 5, cursor: 'pointer',
    fontSize: 14, color: '#94a0b8',
  },

  scList: { margin: 0, padding: '4px 0 8px', listStyle: 'none', background: '#fbfaf6' },
  scRow: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '5px 16px 5px 28px',
    fontSize: 13.5,
  },
  scTick: { color: '#cbd0db', fontSize: 11 },
  scTitle: { fontFamily: 'Lora, Georgia, serif', fontStyle: 'italic', color: '#0f172a' },
  scWords: {
    fontFamily: 'ui-monospace, monospace',
    fontSize: 11.5, color: '#5a6781',
    fontVariantNumeric: 'tabular-nums',
  },

  rightRail: {
    width: 290, flexShrink: 0,
    background: '#fff',
    borderLeft: '1px solid #e7e3d8',
    padding: 16, overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 14,
  },
  cardLabel: {
    fontSize: 10, letterSpacing: '0.14em',
    textTransform: 'uppercase',
    color: '#94a0b8', fontWeight: 600,
    marginBottom: 8,
  },
  progCard: {
    padding: 16, background: '#fbfaf6',
    border: '1px solid #e7e3d8', borderRadius: 8,
  },
  progNum: { display: 'flex', alignItems: 'baseline', gap: 6 },
  progBig: {
    fontFamily: 'Lora, Georgia, serif',
    fontSize: 30, fontWeight: 600,
    fontVariantNumeric: 'tabular-nums',
    letterSpacing: '-0.01em',
  },
  progUnit: { fontSize: 13, color: '#5a6781' },
  progSub: { fontSize: 12.5, color: '#5a6781', marginTop: 4, fontVariantNumeric: 'tabular-nums' },
  progPct: { color: '#0a6e3a', fontWeight: 600 },
  progBarTrack: { height: 4, marginTop: 12, background: '#e7e3d8', borderRadius: 2, overflow: 'hidden' },
  progBarFill: { width: '100%', height: '100%', background: '#1e2a4a' },
  progFoot: { fontSize: 11.5, color: '#5a6781', marginTop: 8, fontStyle: 'italic' },

  cardSection: { padding: 14, background: '#fbfaf6', border: '1px solid #e7e3d8', borderRadius: 8 },
  toolBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center', gap: 9,
    padding: '8px 10px', marginBottom: 2,
    background: 'transparent', border: 'none',
    cursor: 'pointer', fontFamily: 'inherit',
    fontSize: 13.5, color: '#0f172a', borderRadius: 5,
  },
  toolBullet: {
    width: 6, height: 6, borderRadius: '50%',
    background: '#1e2a4a',
  },
  toolArrow: { color: '#cbd0db', fontSize: 13 },
  metaBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center',
    padding: '7px 10px', marginBottom: 1,
    background: 'transparent', border: 'none',
    cursor: 'pointer', fontFamily: 'inherit',
    fontSize: 13, color: '#5a6781', borderRadius: 5,
  },
  activityRow: {
    display: 'flex', gap: 9, padding: '7px 0',
    borderTop: '1px solid #f0ede4',
  },
  activityAv: {
    width: 24, height: 24, borderRadius: '50%',
    background: '#1e2a4a', color: '#fff',
    fontSize: 11, fontWeight: 600,
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    flexShrink: 0,
  },
  activityLine: { fontSize: 12.5, lineHeight: 1.4 },
  activityWhere: { fontFamily: 'Lora, Georgia, serif', fontStyle: 'italic' },
  activityWhen: { fontSize: 11, color: '#94a0b8', marginTop: 2 },
};

window.BibliothequeDirection = BibliothequeDirection;
