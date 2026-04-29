// Shared data for all 4 design directions of the HAINDAL project page.
// Mirrors what's visible in the screenshot of the current Écrivain UI.

const PROJECT = {
  title: "HAINDAL",
  author: "Daniel Rouaix",
  totalWords: 162080,
  pagesWritten: 772,
  pagesGoal: 650,
  progressPct: 100,
  // Left rail: project sections
  sections: [
    { id: "overview",   label: "Vue d'ensemble", active: true },
    { id: "cover",      label: "Couverture" },
    { id: "preface",    label: "Préface" },
    { id: "intro",      label: "Introduction" },
    { id: "prologue",   label: "Prologue" },
    { id: "acts",       label: "Actes",      count: 1 },
    { id: "chapters",   label: "Chapitres",  count: 67 },
    { id: "postface",   label: "Postface" },
    { id: "annexes",    label: "Annexes" },
    { id: "back",       label: "Quatrième de couverture" },
    { id: "structure",  label: "Structure narrative" },
    { id: "add",        label: "À ajouter au livre" },
  ],
  // Top rail: workspace nav
  workspaceNav: [
    { id: "dashboard",     label: "Tableau de bord", icon: "home" },
    { id: "stats",         label: "Statistiques",    icon: "chart" },
    { id: "shares",        label: "Partages",        icon: "share" },
    { id: "templates",     label: "Templates",       icon: "template" },
    { id: "collaborations",label: "Collaborations",  icon: "users" },
  ],
  // Right rail: tools
  tools: [
    { id: "mindmap", label: "Carte mentale" },
    { id: "read",    label: "Mode lecture" },
    { id: "review",  label: "Mode relecture" },
    { id: "ai",      label: "Assistant IA" },
    { id: "export",  label: "Exporter" },
  ],
  meta: [
    { id: "collab",   label: "Collaborateurs" },
    { id: "activity", label: "Activité" },
    { id: "settings", label: "Paramètres" },
  ],
  // Pre-chapter sections (empty group)
  preChapters: [],
  // Acts → chapters → scenes
  acts: [
    {
      id: 1,
      label: "Acte 1",
      chapterCount: 67,
      pageCount: 764,
      chapters: [
        { n: 1, title: "Le contact",     date: "05 février 2025", scenes: 1, words: 1962, expanded: false },
        { n: 2, title: "L'orage",        date: "01 janvier 2025", scenes: 3, words: 3054, expanded: false },
        { n: 3, title: "Sage",           date: "Janvier 2025",    scenes: 3, words: 2880, expanded: false },
        { n: 4, title: "L'équipe",       date: "Janvier 2025",    scenes: 6, words: 2687, expanded: false },
        {
          n: 5, title: "Marseille",      date: "Janvier 2025",    scenes: 5, words: 1926, expanded: true,
          active: true,
          children: [
            { title: "Les Docks",                              words: 79  },
            { title: "Marseille - Arrivée de l'équipe",        words: 528 },
            { title: "Leg présente les bases du projet",       words: 784 },
            { title: "Leg présente Haindal",                   words: 419 },
            { title: "Le pacte",                               words: 122 },
          ],
        },
        { n: 6, title: "Mise en place", date: "Février 2025",   scenes: 3, words: 2510, expanded: true,
          children: [
            { title: "Sage et Leg ferment le chalet",          words: 354  },
            { title: "Déplacement des serveurs de Haindal",    words: 320  },
            { title: "Cartographie d'un refuge",               words: 1836 },
          ],
        },
        { n: 7, title: "Fondation",     date: "Février 2025",   scenes: 2, words: 1958, expanded: false },
        { n: 8, title: "Haindal Agit seule", date: "Mars 2025", scenes: 4, words: 1875, expanded: false },
        { n: 9, title: "Le réseau s'éveille", date: "Mars 2025", scenes: 3, words: 2204, expanded: false },
        { n: 10, title: "Premières fissures", date: "Avril 2025", scenes: 5, words: 2840, expanded: false },
      ],
    },
  ],
  // Recent activity (synthesized — not in screenshot but plausible for "professional" layouts)
  recent: [
    { who: "Vous",   what: "Modifié",   where: "Marseille — Le pacte",          when: "il y a 12 min" },
    { who: "Sage L.", what: "Commenté",  where: "Le réseau s'éveille",          when: "il y a 1 h" },
    { who: "Vous",   what: "Ajouté",    where: "Cartographie d'un refuge",     when: "Hier, 22:14" },
  ],
  goals: {
    todayWords: 1284,
    todayGoal: 1000,
    streak: 23,
  },
};

window.PROJECT = PROJECT;
