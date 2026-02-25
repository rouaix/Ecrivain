# COLAB.md — Spécification : Travail Collaboratif

## Vue d'ensemble

Un **collaborateur** est un utilisateur enregistré invité sur un projet par son propriétaire.
Il peut lire, annoter, exporter et soumettre des propositions de modification.
**Seul le propriétaire valide et applique les changements.**

---

## 1. Modèle de données

### 1.1 `project_collaborators` — invitations

```sql
CREATE TABLE IF NOT EXISTS `project_collaborators` (
    `id`           INT NOT NULL AUTO_INCREMENT,
    `project_id`   INT NOT NULL,
    `owner_id`     INT NOT NULL,          -- user_id du propriétaire
    `user_id`      INT NOT NULL,          -- user_id du collaborateur
    `status`       ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `accepted_at`  TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.2 `collaboration_requests` — propositions de modification

```sql
CREATE TABLE IF NOT EXISTS `collaboration_requests` (
    `id`               INT NOT NULL AUTO_INCREMENT,
    `project_id`       INT NOT NULL,
    `user_id`          INT NOT NULL,          -- collaborateur auteur de la demande
    `request_type`     ENUM('add','modify','delete','correct') NOT NULL,
    `content_type`     VARCHAR(20) NOT NULL,  -- chapter | act | section | note | element | character
    `content_id`       INT NULL,              -- NULL pour type=add
    `content_title`    VARCHAR(255) NULL,     -- titre proposé (add/modify)
    `current_snapshot` LONGTEXT NULL,         -- copie du contenu au moment de la demande
    `proposed_content` LONGTEXT NULL,         -- nouveau contenu proposé
    `message`          TEXT NULL,             -- note libre du collaborateur
    `status`           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    `owner_note`       TEXT NULL,             -- réponse du propriétaire
    `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_at`      TIMESTAMP NULL,
    `reviewed_by`      INT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_project` (`project_id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Fichiers de migration** : `009_project_collaborators.sql` et `010_collaboration_requests.sql`

---

## 2. Nouveau module `collab`

```
src/app/modules/collab/
├── controllers/
│   ├── CollabInviteController.php   — gestion des invitations (propriétaire)
│   └── CollabRequestController.php  — soumission et revue des demandes
└── views/
    ├── collab/
    │   ├── invite.html               — formulaire d'invitation (propriétaire)
    │   ├── pending.html              — liste des invitations en attente (collaborateur)
    │   ├── requests_owner.html       — file de revue des demandes (propriétaire)
    │   └── requests_collab.html      — mes demandes soumises (collaborateur)
    └── partials/
        ├── request_card.html         — carte d'une demande (avec diff)
        └── propose_modal.html        — modal de soumission d'une demande
```

---

## 3. Routes à ajouter dans `config.ini`

```ini
; Collaboration — invitations (propriétaire)
GET  /project/@pid/collaborateurs=CollabInviteController->index
POST /project/@pid/collaborateurs/invite=CollabInviteController->invite
POST /project/@pid/collaborateurs/@uid/remove=CollabInviteController->remove

; Collaboration — invitation reçue (collaborateur)
GET  /collab/invitations=CollabInviteController->myInvitations
POST /collab/invitation/@id/accept=CollabInviteController->accept
POST /collab/invitation/@id/decline=CollabInviteController->decline

; Collaboration — demandes (collaborateur → soumet)
POST /project/@pid/collab/request/add=CollabRequestController->submit
GET  /project/@pid/collab/mes-demandes=CollabRequestController->myRequests
POST /collab/request/@rid/cancel=CollabRequestController->cancel

; Collaboration — revue des demandes (propriétaire)
GET  /project/@pid/collab/demandes=CollabRequestController->ownerQueue
POST /collab/request/@rid/approve=CollabRequestController->approve
POST /collab/request/@rid/reject=CollabRequestController->reject
```

---

## 4. Contrôle d'accès

### 4.1 Helpers à ajouter dans `Controller.php`

```php
// Retourne true si l'utilisateur courant est propriétaire du projet
protected function isOwner(int $projectId): bool

// Retourne true si l'utilisateur courant est collaborateur accepté du projet
protected function isCollaborator(int $projectId): bool

// Retourne true si l'utilisateur courant a accès (owner OU collaborateur accepté)
protected function hasProjectAccess(int $projectId): bool
```

### 4.2 Matrice des droits

| Action                          | Propriétaire | Collaborateur | Sans accès |
|---------------------------------|:------------:|:-------------:|:----------:|
| Voir le projet                  | ✅           | ✅            | ❌         |
| Lire les chapitres              | ✅           | ✅            | ❌         |
| Modifier / créer / supprimer    | ✅           | ❌            | ❌         |
| Réordonner les éléments         | ✅           | ❌            | ❌         |
| Annoter (relecture)             | ✅           | ✅ (propres)  | ❌         |
| Voir les annotations des autres | ✅           | ❌            | ❌         |
| Exporter                        | ✅           | ✅            | ❌         |
| Soumettre une demande           | ❌           | ✅            | ❌         |
| Valider / refuser une demande   | ✅           | ❌            | ❌         |
| Inviter des collaborateurs      | ✅           | ❌            | ❌         |
| Supprimer le projet             | ✅           | ❌            | ❌         |

### 4.3 Modifications des controllers existants

**`ProjectController`**
- `show()` : autoriser `hasProjectAccess()` au lieu de `isOwner()`
- `edit()`, `update()`, `delete()` : bloquer si collaborateur (403)
- `export*()` : autoriser `hasProjectAccess()`
- `dashboard()` : ajouter une section "Projets partagés" avec les projets où l'user est collaborateur accepté

**`ReviewController`**
- `review()` : autoriser `hasProjectAccess()` ; le collaborateur ne voit que ses propres annotations
- `addAnnotation()` : autoriser si `hasProjectAccess()`
- `deleteAnnotation()` : vérifier que l'annotation appartient à l'utilisateur courant (déjà implicite)

**`ChapterController`, `ActController`, `SectionController`, `NoteController`, `ElementController`, `CharacterController`**
- Toutes les routes d'édition/création/suppression : bloquer si collaborateur
- Les routes de lecture (`show()`) : autoriser si `hasProjectAccess()`

---

## 5. UX — Interface propriétaire

### 5.1 Onglet "Collaborateurs" dans la page projet
- Accessible depuis `GET /project/@pid/collaborateurs`
- Affiche la liste des collaborateurs actuels avec leur statut
- Champ email pour inviter un utilisateur existant
- Bouton supprimer pour retirer un collaborateur

### 5.2 Badge de notification dans le header
- Affiche le nombre total de demandes `pending` sur tous les projets du propriétaire
- Icône avec badge rouge (ex. `<i class="fas fa-inbox"> <span class="badge">3</span>`)
- Implémentation : requête SQL dans le layout `main.html` injectée via `Controller->render()`

### 5.3 File de revue des demandes (`requests_owner.html`)
Pour chaque demande, afficher :
- Auteur, date, type de demande, sur quel contenu
- **Diff visuel** : `current_snapshot` vs `proposed_content` (diff ligne par ligne côté JS, ou simple affichage côté à côté)
- Boutons : **Approuver** (applique le changement) / **Refuser** (avec champ note optionnel)

### 5.4 Application automatique à l'approbation (`approve()`)
Selon `request_type` et `content_type`, le controller exécute la modification correspondante :

| `request_type` | `content_type` | Action exécutée                                       |
|----------------|----------------|-------------------------------------------------------|
| `modify`       | `chapter`      | UPDATE chapters SET content = proposed_content        |
| `modify`       | `note`         | UPDATE notes SET content = proposed_content           |
| `modify`       | `section`      | UPDATE sections SET content = proposed_content        |
| `modify`       | `character`    | UPDATE characters SET ... (champs JSON proposés)      |
| `correct`      | tout           | Idem modify (correction mineure)                      |
| `add`          | `chapter`      | INSERT chapter (avec content_title + proposed_content)|
| `add`          | `note`         | INSERT note                                           |
| `delete`       | tout           | Appel au controller de suppression correspondant      |

En cas d'erreur (contenu entre-temps supprimé), renvoyer une erreur propre sans appliquer.

---

## 6. UX — Interface collaborateur

### 6.1 Vue projet en lecture seule
- Mêmes éléments que pour le propriétaire mais sans les boutons d'édition
- Bandeau discret en haut : `« Vous collaborez à ce projet — lecture seule »`
- Chaque chapitre/section/note dispose d'un bouton **"Proposer une modification"**

### 6.2 Modal de soumission (`propose_modal.html`)
Champs selon le type de demande :

**Modifier / Corriger**
- Affichage du contenu actuel (lecture seule)
- Éditeur Quill avec `proposed_content` pré-rempli avec le contenu actuel
- Champ `message` libre (optionnel)

**Ajouter**
- Champ titre
- Éditeur Quill vide pour `proposed_content`
- Sélecteur de position (après quel élément)
- Champ `message`

**Supprimer**
- Aperçu du contenu à supprimer
- Champ `message` obligatoire (justification)

### 6.3 Suivi de mes demandes (`requests_collab.html`)
- Liste de toutes les demandes soumises sur ce projet
- Statut : `En attente` / `Approuvée ✅` / `Refusée ❌`
- Note du propriétaire en cas de refus
- Bouton "Annuler" pour les demandes encore `pending`

### 6.4 Relecture
- Accès à `GET /project/@pid/relecture` (annotations propres uniquement)
- Le collaborateur ne voit pas les annotations du propriétaire
- Peut ajouter / supprimer ses propres annotations normalement

### 6.5 Export
- Accès à toutes les routes `/project/@pid/export/*`
- Pas de restriction particulière

---

## 7. Dashboard — section "Projets partagés"

Dans `ProjectController->dashboard()`, ajouter une requête :

```sql
SELECT p.*, pc.status, u.username AS owner_username
FROM projects p
JOIN project_collaborators pc ON pc.project_id = p.id
JOIN users u ON u.id = p.user_id
WHERE pc.user_id = ?
  AND pc.status = 'accepted'
ORDER BY p.updated_at DESC
```

Afficher ces projets dans une section distincte (`Projets partagés`) sous les projets personnels, avec une icône différente.

---

## 8. État d'avancement

| # | Étape | Statut | Fichiers créés / modifiés |
|---|-------|--------|--------------------------|
| 1 | Migrations SQL | ✅ Fait | `011_project_collaborators.sql`, `012_collaboration_requests.sql` |
| 2 | Helpers accès Controller | ✅ Fait | `Controller.php` — `isOwner()`, `isCollaborator()`, `hasProjectAccess()`, `pendingCollabCount()` |
| 3 | CollabInviteController | ✅ Fait | `collab/controllers/CollabInviteController.php` + vues `invite.html`, `my_invitations.html` |
| 4 | Accès lecture collaborateur | ✅ Fait | `ProjectController.show()`, `ReviewController.loadContent()`, `addAnnotation()`, `report()`, export |
| 5 | Dashboard projets partagés | ✅ Fait | `ProjectController.dashboard()`, `dashboard.html` |
| 6 | CollabRequestController | ✅ Fait | `collab/controllers/CollabRequestController.php` + vues `requests_owner.html`, `requests_collab.html` |
| 7 | Routes + badge header | ✅ Fait | `config.ini`, `layouts/main.html` |
| 8 | Bouton "Proposer" dans show.html | ✅ Fait | `show.html` (modal propose), `inc/new_body.html` (boutons), `inc/header.html` (liens collab) |
| 9 | Accès collaborateur show.html | ✅ Fait | `inc/new_body.html` — CSS `.collab-mode` masque edit/delete/reorder/IA |
| 10 | LectureController accès collab | ✅ Fait | `LectureController.read()`, `saveBookmark()`, `getBookmark()` — `hasProjectAccess()` ; `addComment()` reste owner-only |

## 9. Ordre d'implémentation suggéré (historique)

1. **Migrations** `009` et `010` — créer les tables
2. **Helpers** `isOwner()`, `isCollaborator()`, `hasProjectAccess()` dans `Controller.php`
3. **`CollabInviteController`** — invitation, acceptation, refus, suppression
4. **Accès collaborateur en lecture** — modifier `ProjectController->show()` et les controllers de contenu
5. **Dashboard** — section "Projets partagés"
6. **Annotations en relecture** — vérifier l'accès dans `ReviewController`
7. **Exports** — vérifier l'accès dans `ProjectController->export*()`
8. **`CollabRequestController`** — soumission des demandes
9. **File de revue et application automatique** — côté propriétaire
10. **Badge de notification** dans `main.html`
11. **Modal de proposition** côté collaborateur dans les vues de contenu
