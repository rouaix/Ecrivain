<?php

/**
 * AgentOutputService — applique le résultat d'un agent à sa source selon output_mode.
 *
 *  - display : ne touche à rien (le résultat est seulement affiché) ;
 *  - replace : remplace le contenu de la source par le texte produit ;
 *  - append  : ajoute le texte produit à la fin du contenu de la source.
 *
 * Le write-back n'est possible que pour une source UNIQUE de type « à contenu »
 * (chapter, act, section, note, element, character). La propriété est revérifiée.
 */
class AgentOutputService
{
    private \DB\SQL $db;
    private int $userId;

    /** type de contenu → [classe modèle, colonne de contenu]. */
    private const MAP = [
        'chapter'   => [\Chapter::class,   'content'],
        'act'       => [\Act::class,       'content'],
        'section'   => [\Section::class,   'content'],
        'note'      => [\Note::class,      'content'],
        'element'   => [\Element::class,   'content'],
        'character' => [\Character::class, 'description'],
    ];

    public function __construct(\DB\SQL $db, int $userId)
    {
        $this->db     = $db;
        $this->userId = $userId;
    }

    /** Un type peut-il recevoir un write-back ? */
    public static function supports(string $type): bool
    {
        return isset(self::MAP[$type]);
    }

    /**
     * Applique le résultat. Retourne ['applied' => bool, 'message' => string].
     */
    public function apply(string $mode, string $type, int $id, string $text): array
    {
        if ($mode === 'display') {
            return ['applied' => false, 'message' => ''];
        }
        if (!isset(self::MAP[$type])) {
            return ['applied' => false, 'message' => "Le type « $type » ne peut pas être modifié automatiquement."];
        }

        [$class, $column] = self::MAP[$type];

        $model = new $class($this->db);
        $model->load(['id=?', $id]);
        if ($model->dry()) {
            return ['applied' => false, 'message' => 'Source introuvable.'];
        }

        // Revérifier la propriété via le projet.
        $projectModel = new \Project($this->db);
        if (!$projectModel->count(['id=? AND user_id=?', $model->project_id, $this->userId])) {
            return ['applied' => false, 'message' => 'Accès non autorisé à la source.'];
        }

        if ($mode === 'append') {
            $existing = (string) ($model->$column ?? '');
            $model->$column = $existing === '' ? $text : $existing . "\n\n" . $text;
        } else { // replace
            $model->$column = $text;
        }
        $model->save();

        $label = $mode === 'append' ? 'ajouté à' : 'enregistré dans';
        return ['applied' => true, 'message' => "Résultat $label la source."];
    }
}
