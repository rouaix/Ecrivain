// Direction C — "Atelier"
// Warm cream + terracotta accent. Cormorant Garamond display + Inter UI.
// Feel: a binder's atelier — book cover hero, tactile materials.

function AtelierDirection() {
  const P = window.PROJECT;
  const [active, setActive] = React.useState("overview");
  const [expanded, setExpanded] = React.useState({ 5: true, 6: true });
  const toggle = (n) => setExpanded(e => ({ ...e, [n]: !e[n] }));

  return (
    <div data-screen-label="C · Atelier" style={cStyles.root}>
      <header style={cStyles.topbar}>
        <div style={cStyles.brandRow}>
          <div style={cStyles.brandMark}>É</div>
          <div style={cStyles.brand}>Écrivain</div>
          <span style={cStyles.brandTag}>atelier</span>
        </div>
        <div style={cStyles.crumbRow}>
          <span style={cStyles.crumb}>Mes projets</span>
          <span style={cStyles.crumbSep}>·</span>
          <span style={cStyles.crumbActive}>{P.title}</span>
        </div>
        <div style={cStyles.topbarRight}>
          <button style={cStyles.topBtn}>⌕ Rechercher</button>
          <button style={cStyles.topBtn}>Inviter</button>
          <button style={cStyles.topBtnPrimary}>Reprendre l'écriture</button>
          <div style={cStyles.avatar}>D</div>
        </div>
      </header>

      <div style={cStyles.body}>
        <aside style={cStyles.leftRail}>
          <div style={cStyles.railSection}>
            {P.workspaceNav.map(n => (
              <a key={n.id} style={cStyles.railLink}>{n.label}</a>
            ))}
          </div>

          <div style={cStyles.railLabel}>Manuscrit</div>
          <div style={cStyles.railSection}>
            {P.sections.map(s => (
              <a
                key={s.id}
                onClick={() => setActive(s.id)}
                style={{ ...cStyles.railLink, ...(active === s.id ? cStyles.railLinkActive : null) }}
              >
                <span style={{ flex: 1 }}>{s.label}</span>
                {s.count != null && <span style={cStyles.railCount}>{s.count}</span>}
              </a>
            ))}
          </div>
        </aside>

        <main style={cStyles.center}>
          {/* Hero card */}
          <section style={cStyles.hero}>
            <div style={cStyles.heroCover}>
              <div style={cStyles.heroCoverInner}>
                <div style={cStyles.heroCoverLabel}>UN ROMAN DE</div>
                <div style={cStyles.heroCoverAuthor}>D. ROUAIX</div>
                <div style={cStyles.heroCoverTitle}>HAINDAL</div>
                <div style={cStyles.heroCoverOrn}>❦</div>
                <div style={cStyles.heroCoverFoot}>I.</div>
              </div>
            </div>
            <div style={cStyles.heroText}>
              <div style={cStyles.heroEyebrow}>Roman · en cours d'écriture</div>
              <h1 style={cStyles.heroTitle}>Haindal</h1>
              <div style={cStyles.heroLede}>
                Un thriller technologique en un acte. Cinq mois d'écriture, soixante-sept chapitres,
                cent soixante-deux mille mots posés sur la page.
              </div>
              <div style={cStyles.heroStats}>
                <div style={cStyles.heroStat}>
                  <div style={cStyles.heroStatVal}>162 080</div>
                  <div style={cStyles.heroStatLab}>mots écrits</div>
                </div>
                <div style={cStyles.heroDiv} />
                <div style={cStyles.heroStat}>
                  <div style={cStyles.heroStatVal}>772<span style={cStyles.heroStatSmall}> / 650</span></div>
                  <div style={cStyles.heroStatLab}>pages · objectif dépassé</div>
                </div>
                <div style={cStyles.heroDiv} />
                <div style={cStyles.heroStat}>
                  <div style={cStyles.heroStatVal}>23 j.</div>
                  <div style={cStyles.heroStatLab}>série en cours</div>
                </div>
              </div>
              <div style={cStyles.heroProgBar}><div style={cStyles.heroProgFill} /></div>
            </div>
          </section>

          {/* Section header */}
          <div style={cStyles.sectionHead}>
            <div>
              <h2 style={cStyles.h2}>Actes &amp; chapitres</h2>
              <div style={cStyles.h2sub}>1 acte · 67 chapitres · 764 pages</div>
            </div>
            <div style={cStyles.sectionActions}>
              <div style={cStyles.searchInline}>
                <span style={{ opacity: .5 }}>⌕</span>
                <input placeholder="Filtrer…" style={cStyles.searchInput} />
              </div>
              <button style={cStyles.btnGhost}>Trier</button>
              <button style={cStyles.btnPrimary}>+ Chapitre</button>
            </div>
          </div>

          <section style={cStyles.group}>
            <header style={cStyles.groupHeader}>
              <span style={cStyles.chev}>›</span>
              <span style={cStyles.groupTitle}>Sections avant les chapitres</span>
              <span style={cStyles.groupBadge}>0</span>
            </header>
          </section>

          {P.acts.map(act => (
            <section key={act.id} style={cStyles.group}>
              <header style={{ ...cStyles.groupHeader, ...cStyles.groupHeaderOpen }}>
                <span style={cStyles.chevOpen}>⌄</span>
                <span style={cStyles.groupTitle}>{act.label}</span>
                <span style={{ flex: 1 }} />
                <span style={cStyles.groupMeta}>{act.chapterCount} chapitres · {act.pageCount} p.</span>
              </header>

              <ol style={cStyles.chList}>
                {act.chapters.map(ch => (
                  <li key={ch.n} style={ch.active ? cStyles.chRowActive : cStyles.chRow}>
                    <div style={cStyles.chHead} onClick={() => toggle(ch.n)}>
                      <div style={cStyles.chBadge}>{String(ch.n).padStart(2, '0')}</div>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={cStyles.chTitle}>{ch.title}</div>
                        <div style={cStyles.chSub}>{ch.date} · {ch.scenes} scène{ch.scenes > 1 ? 's' : ''}</div>
                      </div>
                      <div style={cStyles.chWordsCol}>
                        <div style={cStyles.chWords}>{ch.words.toLocaleString('fr-FR')}</div>
                        <div style={cStyles.chWordsLab}>mots</div>
                      </div>
                      <button style={cStyles.expand}>{expanded[ch.n] ? '⌄' : '›'}</button>
                    </div>
                    {expanded[ch.n] && ch.children && (
                      <ul style={cStyles.scList}>
                        {ch.children.map((s, i) => (
                          <li key={i} style={cStyles.scRow}>
                            <span style={cStyles.scNum}>{String(i + 1).padStart(2, '0')}</span>
                            <span style={cStyles.scTitle}>{s.title}</span>
                            <span style={{ flex: 1 }} />
                            <span style={cStyles.scWords}>{s.words.toLocaleString('fr-FR')} mots</span>
                          </li>
                        ))}
                        <li style={{ ...cStyles.scRow, color: '#a89472' }}>
                          <span style={cStyles.scNum}>＋</span>
                          <span style={cStyles.scTitle}>Ajouter une scène</span>
                        </li>
                      </ul>
                    )}
                  </li>
                ))}
              </ol>
            </section>
          ))}
        </main>

        <aside style={cStyles.rightRail}>
          <div style={cStyles.todayCard}>
            <div style={cStyles.todayLabel}>Aujourd'hui</div>
            <div style={cStyles.todayBig}>1 284</div>
            <div style={cStyles.todayUnit}>mots · objectif 1 000 dépassé</div>
            <div style={cStyles.todayBar}><div style={cStyles.todayBarFill} /></div>
          </div>

          <div style={cStyles.toolsCard}>
            <div style={cStyles.toolsLabel}>Outils</div>
            {P.tools.map(t => (
              <button key={t.id} style={cStyles.toolBtn}>
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={cStyles.toolArrow}>→</span>
              </button>
            ))}
          </div>

          <div style={cStyles.toolsCard}>
            <div style={cStyles.toolsLabel}>Activité</div>
            {P.recent.map((r, i) => (
              <div key={i} style={cStyles.activityRow}>
                <div style={cStyles.activityDot} />
                <div style={{ flex: 1 }}>
                  <div style={cStyles.activityLine}>
                    <strong>{r.who}</strong> · {r.what.toLowerCase()}
                  </div>
                  <div style={cStyles.activityWhere}>{r.where}</div>
                  <div style={cStyles.activityWhen}>{r.when}</div>
                </div>
              </div>
            ))}
          </div>

          <div style={cStyles.toolsCard}>
            {P.meta.map(t => (
              <button key={t.id} style={cStyles.metaBtn}>
                <span style={{ flex: 1, textAlign: 'left' }}>{t.label}</span>
                <span style={cStyles.toolArrow}>→</span>
              </button>
            ))}
          </div>
        </aside>
      </div>
    </div>
  );
}

const cStyles = {
  root: {
    width: '100%', height: '100%',
    background: '#f6efe2',
    color: '#2a2218',
    fontFamily: 'Inter, "Helvetica Neue", system-ui, sans-serif',
    fontSize: 13.5, lineHeight: 1.45,
    overflow: 'hidden',
    display: 'flex', flexDirection: 'column',
  },

  topbar: {
    height: 56, flexShrink: 0,
    background: '#3a2418',
    color: '#f6efe2',
    display: 'flex', alignItems: 'center',
    padding: '0 22px', gap: 22,
  },
  brandRow: { display: 'flex', alignItems: 'center', gap: 10 },
  brandMark: {
    width: 30, height: 30, borderRadius: 4,
    background: '#c4623c', color: '#f6efe2',
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 19, fontWeight: 600, fontStyle: 'italic',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
  },
  brand: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 21, fontWeight: 500, fontStyle: 'italic',
    letterSpacing: '0.01em',
  },
  brandTag: {
    fontSize: 10, letterSpacing: '0.22em',
    textTransform: 'uppercase',
    color: '#c4a575', fontWeight: 600,
    paddingLeft: 8, marginLeft: 4,
    borderLeft: '1px solid rgba(196,165,117,.4)',
  },
  crumbRow: { display: 'flex', alignItems: 'center', gap: 10, flex: 1, color: '#d4c4a8' },
  crumb: { fontSize: 13 },
  crumbSep: { color: '#7a6045' },
  crumbActive: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 16, fontWeight: 600, color: '#f6efe2', fontStyle: 'italic',
  },
  topbarRight: { display: 'flex', alignItems: 'center', gap: 8 },
  topBtn: {
    height: 32, padding: '0 12px',
    background: 'rgba(255,255,255,0.07)',
    border: '1px solid rgba(255,255,255,0.12)',
    color: '#f6efe2', borderRadius: 4, fontSize: 12.5,
    cursor: 'pointer', fontFamily: 'inherit',
  },
  topBtnPrimary: {
    height: 32, padding: '0 14px',
    background: '#c4623c', color: '#fff', border: 'none',
    borderRadius: 4, fontSize: 13, fontWeight: 500,
    cursor: 'pointer', fontFamily: 'inherit',
  },
  avatar: {
    width: 32, height: 32, borderRadius: '50%',
    background: '#c4a575', color: '#3a2418',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    fontSize: 13, fontWeight: 700,
  },

  body: { flex: 1, display: 'flex', minHeight: 0 },

  leftRail: {
    width: 220, flexShrink: 0,
    background: '#ede4d2',
    borderRight: '1px solid #ddd0b8',
    padding: '18px 12px',
    overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 4,
  },
  railSection: { display: 'flex', flexDirection: 'column', gap: 1, marginBottom: 14 },
  railLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#8a7558', fontWeight: 600,
    padding: '4px 10px', marginTop: 4,
  },
  railLink: {
    display: 'flex', alignItems: 'center',
    padding: '6px 10px',
    fontSize: 13.5, color: '#4a3826',
    cursor: 'pointer', borderRadius: 3,
  },
  railLinkActive: {
    background: '#3a2418', color: '#f6efe2',
    fontWeight: 500,
  },
  railCount: {
    fontSize: 11, color: '#8a7558',
    fontVariantNumeric: 'tabular-nums',
  },

  center: {
    flex: 1, overflowY: 'auto',
    padding: '24px 32px 48px',
    background: '#f6efe2',
  },

  hero: {
    display: 'flex', gap: 28,
    padding: 24,
    background: '#fdf8ec',
    border: '1px solid #ddd0b8',
    borderRadius: 6,
    marginBottom: 28,
  },
  heroCover: {
    width: 180, aspectRatio: '2 / 3', flexShrink: 0,
    background: 'linear-gradient(160deg, #2a1810 0%, #4a2a1c 100%)',
    border: '1px solid #1a0e08',
    boxShadow: '4px 4px 0 #ddd0b8, 4px 4px 0 1px #2a1810',
    padding: 14,
    color: '#e8d5b2',
    display: 'flex', flexDirection: 'column',
  },
  heroCoverInner: {
    border: '1px solid rgba(232,213,178,0.3)',
    flex: 1, padding: '16px 10px',
    display: 'flex', flexDirection: 'column', alignItems: 'center',
    textAlign: 'center', gap: 8,
  },
  heroCoverLabel: { fontSize: 9, letterSpacing: '0.25em', opacity: .65 },
  heroCoverAuthor: { fontSize: 10, opacity: .8, letterSpacing: '0.1em' },
  heroCoverTitle: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 30, fontWeight: 600, fontStyle: 'italic',
    letterSpacing: '0.03em',
    marginTop: 'auto', marginBottom: 'auto',
  },
  heroCoverOrn: { fontSize: 16, color: '#c4a575' },
  heroCoverFoot: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 14, fontStyle: 'italic', opacity: .7,
  },

  heroText: { flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' },
  heroEyebrow: {
    fontSize: 11, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#c4623c', fontWeight: 600,
  },
  heroTitle: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 48, fontWeight: 500,
    margin: '6px 0 10px',
    fontStyle: 'italic', letterSpacing: '-0.01em',
    lineHeight: 1.05,
  },
  heroLede: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 17, lineHeight: 1.45,
    color: '#4a3826',
    maxWidth: 520,
  },
  heroStats: {
    display: 'flex', alignItems: 'center', gap: 24,
    marginTop: 22, paddingTop: 18,
    borderTop: '1px solid #e8dcc4',
  },
  heroStat: { },
  heroStatVal: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 26, fontWeight: 600,
    fontVariantNumeric: 'tabular-nums',
    letterSpacing: '-0.01em',
  },
  heroStatSmall: { fontSize: 14, color: '#8a7558', fontWeight: 400 },
  heroStatLab: { fontSize: 11.5, color: '#8a7558', marginTop: 2 },
  heroDiv: { width: 1, height: 32, background: '#ddd0b8' },
  heroProgBar: {
    height: 3, background: '#e8dcc4',
    marginTop: 16, borderRadius: 2, overflow: 'hidden',
  },
  heroProgFill: { width: '100%', height: '100%', background: '#c4623c' },

  sectionHead: {
    display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end',
    marginBottom: 18, gap: 16,
    paddingBottom: 12, borderBottom: '1px solid #ddd0b8',
  },
  h2: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 28, fontWeight: 600,
    margin: 0, fontStyle: 'italic',
  },
  h2sub: { fontSize: 12.5, color: '#8a7558', marginTop: 2 },
  sectionActions: { display: 'flex', alignItems: 'center', gap: 8 },
  searchInline: {
    display: 'flex', alignItems: 'center', gap: 6,
    height: 30, padding: '0 10px',
    background: '#fdf8ec', border: '1px solid #ddd0b8',
    borderRadius: 4, fontSize: 13, width: 180,
  },
  searchInput: {
    border: 'none', outline: 'none', background: 'transparent',
    flex: 1, fontFamily: 'inherit', fontSize: 13, color: '#2a2218',
  },
  btnGhost: {
    height: 30, padding: '0 12px',
    background: '#fdf8ec', border: '1px solid #ddd0b8',
    borderRadius: 4, fontSize: 13, fontFamily: 'inherit',
    cursor: 'pointer', color: '#2a2218',
  },
  btnPrimary: {
    height: 30, padding: '0 14px',
    background: '#c4623c', color: '#fff', border: 'none',
    borderRadius: 4, fontSize: 13, fontWeight: 500,
    cursor: 'pointer', fontFamily: 'inherit',
  },

  group: {
    marginBottom: 14,
    background: '#fdf8ec',
    border: '1px solid #ddd0b8',
    borderRadius: 6, overflow: 'hidden',
  },
  groupHeader: {
    display: 'flex', alignItems: 'center', gap: 10,
    padding: '12px 18px',
  },
  groupHeaderOpen: { borderBottom: '1px solid #ebe0c8' },
  chev: { fontSize: 14, color: '#8a7558', width: 12 },
  chevOpen: { fontSize: 14, color: '#3a2418', width: 12 },
  groupTitle: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 18, fontWeight: 600, fontStyle: 'italic',
  },
  groupBadge: {
    fontSize: 11, padding: '1px 8px',
    background: '#ede4d2', color: '#8a7558',
    borderRadius: 10, fontVariantNumeric: 'tabular-nums',
  },
  groupMeta: { fontSize: 12, color: '#8a7558', fontVariantNumeric: 'tabular-nums' },

  chList: { margin: 0, padding: 0, listStyle: 'none' },
  chRow: { borderBottom: '1px solid #ebe0c8' },
  chRowActive: {
    borderBottom: '1px solid #ebe0c8',
    background: 'linear-gradient(90deg, rgba(196,98,60,0.08), rgba(196,98,60,0))',
    boxShadow: 'inset 3px 0 0 #c4623c',
  },
  chHead: {
    display: 'flex', alignItems: 'center', gap: 14,
    padding: '14px 18px', cursor: 'pointer',
  },
  chBadge: {
    width: 38, height: 38, borderRadius: '50%',
    background: '#3a2418', color: '#f6efe2',
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 14, fontWeight: 600, fontStyle: 'italic',
    display: 'flex', alignItems: 'center', justifyContent: 'center',
    flexShrink: 0,
    fontVariantNumeric: 'tabular-nums',
  },
  chTitle: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 17, fontWeight: 600,
    fontStyle: 'italic',
  },
  chSub: { fontSize: 12, color: '#8a7558', marginTop: 1 },
  chWordsCol: { textAlign: 'right' },
  chWords: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 18, fontWeight: 600,
    fontVariantNumeric: 'tabular-nums',
  },
  chWordsLab: { fontSize: 10.5, color: '#8a7558' },
  expand: {
    width: 28, height: 28,
    background: 'transparent', border: 'none',
    color: '#8a7558', fontSize: 16,
    cursor: 'pointer', borderRadius: '50%',
  },

  scList: {
    margin: 0, padding: '6px 0 12px',
    listStyle: 'none',
    background: '#f6ecd6',
    borderTop: '1px dashed #ddd0b8',
  },
  scRow: {
    display: 'flex', alignItems: 'center', gap: 14,
    padding: '6px 18px 6px 70px',
    fontSize: 13.5,
  },
  scNum: {
    fontFamily: 'ui-monospace, monospace',
    fontSize: 11, color: '#a89472',
    fontVariantNumeric: 'tabular-nums',
    width: 18,
  },
  scTitle: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 15, fontStyle: 'italic',
  },
  scWords: {
    fontSize: 12, color: '#8a7558',
    fontVariantNumeric: 'tabular-nums',
  },

  rightRail: {
    width: 270, flexShrink: 0,
    background: '#ede4d2',
    borderLeft: '1px solid #ddd0b8',
    padding: 16, overflowY: 'auto',
    display: 'flex', flexDirection: 'column', gap: 12,
  },
  todayCard: {
    padding: 16,
    background: '#3a2418', color: '#f6efe2',
    borderRadius: 6,
  },
  todayLabel: {
    fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase',
    color: '#c4a575', fontWeight: 600,
  },
  todayBig: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 38, fontWeight: 600, fontStyle: 'italic',
    fontVariantNumeric: 'tabular-nums',
    marginTop: 6, lineHeight: 1,
  },
  todayUnit: { fontSize: 12, color: '#d4c4a8', marginTop: 6 },
  todayBar: {
    height: 3, background: 'rgba(255,255,255,0.12)',
    marginTop: 12, borderRadius: 2, overflow: 'hidden',
  },
  todayBarFill: { width: '100%', height: '100%', background: '#c4623c' },

  toolsCard: {
    background: '#fdf8ec',
    border: '1px solid #ddd0b8',
    borderRadius: 6, padding: 6,
  },
  toolsLabel: {
    fontSize: 10, letterSpacing: '0.16em',
    textTransform: 'uppercase',
    color: '#8a7558', fontWeight: 600,
    padding: '8px 10px 4px',
  },
  toolBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center',
    padding: '9px 10px',
    background: 'transparent', border: 'none',
    cursor: 'pointer', fontFamily: 'inherit',
    fontSize: 13.5, color: '#2a2218', borderRadius: 4,
  },
  toolArrow: { color: '#a89472', fontSize: 14 },

  activityRow: {
    display: 'flex', gap: 10, padding: '8px 10px',
  },
  activityDot: {
    width: 6, height: 6, borderRadius: '50%',
    background: '#c4623c', marginTop: 6, flexShrink: 0,
  },
  activityLine: { fontSize: 12.5 },
  activityWhere: {
    fontFamily: '"Cormorant Garamond", Georgia, serif',
    fontSize: 13, fontStyle: 'italic', color: '#4a3826',
    marginTop: 1,
  },
  activityWhen: { fontSize: 11, color: '#a89472', marginTop: 2 },
  metaBtn: {
    width: '100%',
    display: 'flex', alignItems: 'center',
    padding: '8px 10px',
    background: 'transparent', border: 'none',
    cursor: 'pointer', fontFamily: 'inherit',
    fontSize: 13, color: '#5a4836', borderRadius: 4,
  },
};

window.AtelierDirection = AtelierDirection;
