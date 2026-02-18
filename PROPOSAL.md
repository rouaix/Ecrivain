# PROPOSAL.md — Optimisations et nouvelles fonctionnalités

Propositions basées sur l'analyse du code source actuel (février 2026).

---

## Corrections critiques

### 1. SSL désactivé dans AiService ⚠️ ✅ RÉALISÉ — branche `fix/ssl-verification`

**Fichier :** `src/app/modules/ai/models/AiService.php`

```php
// Actuellement — vulnérable aux attaques man-in-the-middle
'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]

// À corriger
'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
```

Toutes les clés API (OpenAI, Anthropic, etc.) transitent sans vérification TLS en production.

---

### 2. JWT_SECRET non requis au démarrage ✅ RÉALISÉ — branche `fix/jwt-secret-guard`

**Fichier :** `src/www/index.php`

Si `JWT_SECRET` est absent en production, il tombe à `null` silencieusement. Ajouter un guard en production :

```php
if (!$jwtSecret && !$isLocal) {
    http_response_code(500);
    die('Configuration error: JWT_SECRET is required.');
}
```

---

## Optimisations techniques

### 3. Auto-save avec indicateur visuel ✅ RÉALISÉ — branche `feature/autosave`

**Contexte :** L'éditeur de chapitre ne sauvegarde que sur clic explicite. Perte de données possible.

**Proposition :**
- Auto-save toutes les 30 secondes si le contenu a changé
- Indicateur discret dans la barre : « Enregistré il y a 2 min » / « Modifications non enregistrées »
- Alerte `beforeunload` si des modifications n'ont pas été sauvées
- Stocker le draft en `localStorage` comme filet de sécurité supplémentaire

---

### 4. Estimation des tokens IA ✅ RÉALISÉ — branche `fix/accurate-token-counting`

**Fichier :** `src/app/modules/ai/controllers/AiController.php`

```php
// Actuellement : division par 4 (approximation grossière)
$promptTokens = ceil((strlen($system) + strlen($userPrompt)) / 4);
```

La tokenisation varie selon le fournisseur et le modèle (GPT-4o vs Claude vs Gemini). Erreur possible de 20–30 %.

**Proposition :** Utiliser le nombre de tokens retourné dans la réponse API (tous les fournisseurs le fournissent dans `usage`), plutôt qu'une estimation avant l'appel. Le suivi `ai_usage` serait alors exact.

---

### 5. Rate limiting sur les endpoints IA ✅ RÉALISÉ — branche `feature/ai-rate-limiting`

Aucune limite de fréquence n'existe sur `/ai/generate`, `/ai/summarize-*`, etc. Un utilisateur peut déclencher des centaines d'appels API en boucle.

**Proposition :** Stocker le timestamp du dernier appel en session côté serveur. Refuser les appels à moins de 5 secondes d'intervalle avec un message clair. Optionnellement, quota journalier configurable par utilisateur.

---

### 6. Optimisation du contexte IA ✅ RÉALISÉ — branche `feature/ai-context-cache`

**Fichier :** `AiController.php::ask()`

À chaque appel, le contexte projet (actes, chapitres, personnages, sections, notes) est rechargé depuis la BDD et tronqué. Problèmes :
- Multiples requêtes SQL distinctes (N+1)
- Recalcul systématique

**Proposition :**
- Assembler le contexte en une seule requête avec `UNION` ou plusieurs colonnes
- Mettre en cache le contexte en session (invalider sur sauvegarde de chapitre)

---

### 7. Liste des modèles IA dynamique ✅ RÉALISÉ — branche `feature/ai-models-json`

**Fichier :** `AiController.php::getModels()` — ~150 modèles codés en dur

Les modèles disponibles évoluent rapidement. Cette liste sera obsolète régulièrement.

**Proposition :** Externaliser dans `src/app/ai_models.json`, avec possibilité de mise à jour sans toucher au code. Structure :

```json
{
  "openai": [
    { "id": "gpt-4o", "label": "GPT-4o", "context": 128000 }
  ],
  "anthropic": [...]
}
```

---

### 8. Gestion mémoire de l'éditeur Quill

**Fichier :** `src/app/modules/chapter/views/editor/edit.html`

Trois instances Quill coexistent (éditeur principal, résumé, commentaire). Aucune n'est détruite à la fermeture des modals, entraînant une accumulation d'écouteurs d'événements.

**Proposition :** Appeler `quill.off()` / mettre l'instance à `null` au `hidden.bs.modal` pour libérer la mémoire.

---

### 9. Performance du mode lecture

**Fichier :** `src/app/modules/lecture/views/lecture/read.html`

- L'événement `scroll` est non déboncé — déclenché à chaque pixel parcouru
- `querySelectorAll('.toc-item')` appelé dans des boucles sans mise en cache

```javascript
// À ajouter
function debounce(fn, delay) {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
}
scrollableDiv.addEventListener('scroll', debounce(updateCurrentPage, 100));
```

---

## Nouvelles fonctionnalités

### 10. Recherche plein texte

**Manque actuel :** Aucun moyen de chercher un mot dans les chapitres, les notes, ou de retrouver toutes les mentions d'un personnage.

**Proposition :**
- Champ de recherche global (raccourci `Ctrl+K`) dans la barre de navigation
- Recherche dans le contenu des chapitres, actes, notes, fiches personnages
- Utiliser `MATCH ... AGAINST` MySQL (FULLTEXT index) ou `LIKE` avec pagination
- Résultats avec extraits contextuels (surbrillance du terme)

---

### 11. Statistiques d'écriture

**Proposition :** Tableau de bord avec :
- Nombre de mots écrits aujourd'hui / cette semaine / ce mois
- Graphique de progression sur 30 jours (mots par jour)
- Répartition longueur des chapitres
- Streak de jours consécutifs d'écriture

Données disponibles : `chapters.word_count` (calculable à la sauvegarde) + horodatage `updated_at`.

---

### 12. Historique des versions de chapitre

**Contexte :** En cas de suppression accidentelle ou de réécriture ratée, le contenu est perdu.

**Proposition :**
- Table `chapter_versions` : `chapter_id`, `content`, `word_count`, `created_at`
- Snapshot automatique à chaque sauvegarde (garder les 10 dernières)
- Interface de comparaison/restauration accessible depuis l'éditeur

---

### 13. Vérification grammaticale intégrée (LanguageTool)

**Contexte :** Le code dans `quill-adapter.js` prépare une intégration LanguageTool mais elle est incomplète.

**Proposition :**
- Soulignement des fautes en temps réel via l'API LanguageTool (auto-hébergeable)
- Dictionnaire personnalisé par projet (noms de personnages, lieux fictifs)
- Mode "correction globale" : passer tout un chapitre en revue

---

### 14. Suivi des personnages dans le texte

**Manque actuel :** Les fiches personnages existent mais sans lien avec le contenu des chapitres.

**Proposition :**
- Détecter automatiquement les mentions des noms de personnages dans les chapitres
- Afficher dans la fiche personnage la liste des chapitres où il/elle apparaît
- Vue "timeline personnage" : ordre chronologique des apparitions

---

### 15. Mode focus / plein écran

**Proposition :**
- Mode d'écriture immersif : masquer la navigation, les panels latéraux, le fond
- Seul le texte reste visible (inspiré de iA Writer / Hemingway Editor)
- Raccourci clavier `F11` ou bouton dans la barre d'outils Quill
- Option : fond uni, typographie centrée, largeur de colonne fixe

---

### 16. Objectifs d'écriture

**Proposition :**
- Objectif de mots par session (ex. : 500 mots aujourd'hui)
- Barre de progression visible dans l'éditeur
- Objectif global par projet (ex. : roman de 80 000 mots)
- Notification discrète à l'atteinte de l'objectif

---

### 17. Export EPUB

**Contexte :** L'export PDF existe (via html2pdf). L'EPUB manque alors que c'est le format standard des liseuses.

**Proposition :**
- Générer un EPUB 3 valide à partir des chapitres
- Inclure : couverture, table des matières automatique, métadonnées (titre, auteur)
- Librairie PHP : `PHPePub` ou génération manuelle du ZIP/XML EPUB
- Accessible depuis le même menu Export que le PDF

---

### 18. Templates de projet améliorés

**Contexte :** Le système de templates existe mais reste basique.

**Proposition :**
- Templates prédéfinis : Roman, Scénario, Nouvelle, Essai, Mémoire
- Chaque template préconfigure les types de sections, les champs personnages, et la structure des actes
- Import/export de templates entre utilisateurs (fichier JSON)

---

### 19. Mode relecture avec annotations

**Proposition :**
- Mode "relecture" distinct du mode "lecture" : surlignage et annotation de passages
- Annotations par catégorie : À reformuler, Incohérence, À vérifier, Bien
- Rapport de relecture : liste de toutes les annotations d'un projet, exportable
- Différent des commentaires actuels (qui sont par position de caractère)

---

### 20. Intégration webhooks / notifications

**Proposition :**
- Notification (email ou webhook configurable) à la fin d'une génération IA longue
- Résumé hebdomadaire des statistiques d'écriture par email
- Alerte si l'usage IA dépasse un seuil configurable (coût)

---

## Améliorations PWA / mobile

### 21. Offline réel avec IndexedDB

**Contexte :** Le service worker est enregistré mais n'implémente pas de stratégie de cache.

**Proposition :**
- Mettre en cache les chapitres consultés dans IndexedDB
- Permettre la lecture hors ligne du projet courant
- Synchroniser les modifications locales au retour de connexion (avec résolution de conflits simple : "serveur gagne" ou "local gagne" au choix)

---

### 22. Interface mobile de l'éditeur

**Constat :** Quill n'est pas optimisé pour le tactile. La barre d'outils est difficilement utilisable sur mobile.

**Proposition :**
- Barre d'outils simplifiée sur mobile (boutons agrandis, outils essentiels uniquement)
- Geste swipe pour naviguer entre chapitres
- Bouton flottant "Sauvegarder" toujours accessible sur mobile

---

## Tableau de priorisation

| # | Fonctionnalité | Impact | Effort | Priorité |
|---|---------------|--------|--------|----------|
| 1 | Correction SSL | Sécurité critique | Faible | 🔴 Immédiat |
| 2 | Guard JWT_SECRET | Sécurité | Faible | 🔴 Immédiat |
| 3 | Auto-save + indicateur | UX majeur | Moyen | 🟠 Haute |
| 10 | Recherche plein texte | UX majeur | Moyen | 🟠 Haute |
| 12 | Historique versions | Sécurité données | Moyen | 🟠 Haute |
| 5 | Rate limiting IA | Technique | Faible | 🟠 Haute |
| 15 | Mode focus | UX | Faible | 🟡 Moyenne |
| 11 | Statistiques écriture | Engagement | Moyen | 🟡 Moyenne |
| 13 | LanguageTool | Fonctionnel | Moyen | 🟡 Moyenne |
| 17 | Export EPUB | Fonctionnel | Moyen | 🟡 Moyenne |
| 16 | Objectifs d'écriture | Engagement | Faible | 🟡 Moyenne |
| 14 | Suivi personnages | Fonctionnel | Moyen | 🟢 Basse |
| 19 | Mode relecture | Fonctionnel | Élevé | 🟢 Basse |
| 4 | Tokens IA exacts | Technique | Faible | 🟢 Basse |
| 7 | Modèles IA JSON | Maintenance | Faible | 🟢 Basse |
| 21 | Offline IndexedDB | PWA | Élevé | 🟢 Basse |
