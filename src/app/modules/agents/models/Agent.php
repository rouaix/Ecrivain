<?php

use KS\Mapper;

/**
 * Agent — un agent IA appartenant à un utilisateur (global, réutilisable sur tous ses projets).
 * Le champ `system_prompt` est le « skill » de l'agent, éditable depuis l'interface.
 */
class Agent extends Mapper
{
    const TABLE = 'ai_agents';

    /** Retourne tous les agents de l'utilisateur, agents actifs en premier puis par nom. */
    public function getAllByUser(int $userId): array
    {
        return $this->findAndCast(
            ['user_id=?', $userId],
            ['order' => 'is_active DESC, name ASC']
        );
    }

    /** Charge un agent en vérifiant qu'il appartient bien à l'utilisateur. */
    public function loadOwned(int $id, int $userId): ?array
    {
        $rows = $this->findAndCast(['id=? AND user_id=?', $id, $userId]);
        return $rows[0] ?? null;
    }

    /**
     * Crée les 4 agents par défaut (Auteur, Relecteur, Correcteur, Documentaliste)
     * et leurs actions pour un utilisateur qui n'en a encore aucun.
     */
    public static function seedDefaults(\DB\SQL $db, int $userId): void
    {
        $agents = [
            [
                'name' => 'Auteur', 'role' => 'Écriture créative', 'icon' => 'feather-pointed', 'color' => '#6366f1',
                'description' => "Rédige et développe le texte : continue un passage, étoffe une scène, propose une réécriture.",
                'system_prompt' => "Tu es un auteur de fiction expérimenté. Tu écris dans un français littéraire soigné, en respectant le style, le ton et la voix narrative du texte fourni. Tu ne commentes pas, tu produis directement le texte demandé.",
                'actions' => [
                    ['label' => 'Continuer le texte', 'action_key' => 'continue', 'output_mode' => 'append', 'max_tokens' => 800,
                     'instruction' => "Continue le texte suivant de façon fluide et cohérente, dans le même style et au même temps narratif."],
                    ['label' => 'Développer / étoffer', 'action_key' => 'expand', 'output_mode' => 'display', 'max_tokens' => 900,
                     'instruction' => "Étoffe le texte suivant : enrichis les descriptions, les sensations et les détails, sans changer l'intrigue."],
                    ['label' => 'Réécrire', 'action_key' => 'rewrite', 'output_mode' => 'display', 'max_tokens' => 900,
                     'instruction' => "Réécris le texte suivant en améliorant le rythme et la clarté, tout en préservant le sens et le style."],
                ],
            ],
            [
                'name' => 'Relecteur', 'role' => 'Critique éditoriale', 'icon' => 'glasses', 'color' => '#0ea5e9',
                'description' => "Porte un regard éditorial : relit, annote les faiblesses et vérifie la cohérence narrative.",
                'system_prompt' => "Tu es un éditeur de maison d'édition. Tu analyses le texte avec bienveillance et exigence. Tu structures tes retours en points clairs et hiérarchisés (rythme, personnages, cohérence, style).",
                'actions' => [
                    ['label' => 'Relire et annoter', 'action_key' => 'review', 'output_mode' => 'display', 'max_tokens' => 1000,
                     'instruction' => "Fais une relecture éditoriale du texte suivant. Liste les points forts, les faiblesses et des suggestions concrètes d'amélioration."],
                    ['label' => 'Vérifier la cohérence', 'action_key' => 'consistency', 'output_mode' => 'display', 'max_tokens' => 1000,
                     'instruction' => "Analyse la cohérence narrative du contenu suivant : contradictions, incohérences temporelles, personnages, lieux. Retourne un rapport d'alertes."],
                ],
            ],
            [
                'name' => 'Correcteur', 'role' => 'Orthographe & style', 'icon' => 'spell-check', 'color' => '#10b981',
                'description' => "Corrige l'orthographe, la grammaire et la typographie, et peut lisser le style.",
                'system_prompt' => "Tu es un correcteur professionnel. Tu corriges sans altérer le sens ni le style de l'auteur. Tu retournes uniquement le texte corrigé, sans explication.",
                'actions' => [
                    ['label' => "Corriger l'orthographe", 'action_key' => 'correct', 'output_mode' => 'replace', 'max_tokens' => 1200,
                     'instruction' => "Corrige l'orthographe, la grammaire, la conjugaison et la typographie du texte suivant. Retourne le texte corrigé."],
                    ['label' => 'Améliorer le style', 'action_key' => 'polish', 'output_mode' => 'display', 'max_tokens' => 1200,
                     'instruction' => "Améliore le style du texte suivant (fluidité, répétitions, lourdeurs) sans en changer le sens."],
                ],
            ],
            [
                'name' => 'Documentaliste', 'role' => 'Synthèse & recherche', 'icon' => 'book', 'color' => '#f59e0b',
                'description' => "Synthétise le contenu et répond aux questions sur le projet à partir des données fournies.",
                'system_prompt' => "Tu es un documentaliste rigoureux. Tu t'appuies uniquement sur les données fournies. Tu es synthétique, factuel et tu cites les éléments pertinents.",
                'actions' => [
                    ['label' => 'Résumer', 'action_key' => 'summarize', 'output_mode' => 'display', 'max_tokens' => 600,
                     'instruction' => "Fais un résumé synthétique et structuré du contenu suivant."],
                    ['label' => 'Répondre à une question', 'action_key' => 'ask', 'output_mode' => 'display', 'max_tokens' => 800,
                     'instruction' => "À partir du contenu fourni, réponds à la question de l'auteur de façon précise et sourcée."],
                ],
            ],
        ];

        foreach ($agents as $a) {
            $db->exec(
                'INSERT INTO ai_agents (user_id, name, role, description, icon, color, system_prompt, is_system)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
                [$userId, $a['name'], $a['role'], $a['description'], $a['icon'], $a['color'], $a['system_prompt']]
            );
            $agentId = (int) $db->lastInsertId();

            $order = 0;
            foreach ($a['actions'] as $act) {
                $db->exec(
                    'INSERT INTO ai_agent_actions (agent_id, label, action_key, instruction, output_mode, max_tokens, order_index)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$agentId, $act['label'], $act['action_key'], $act['instruction'], $act['output_mode'], $act['max_tokens'], $order++]
                );
            }
        }
    }
}
