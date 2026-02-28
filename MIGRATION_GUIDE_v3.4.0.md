# Guide de Migration : Postal Warmup Pro v3.4.0

## 1. Pré-requis et Sauvegarde
Avant de procéder à la mise à jour, il est impératif de réaliser une sauvegarde complète.

1.  **Base de données :** Exportez toutes les tables commençant par `wp_postal_` (ou votre préfixe).
2.  **Fichiers :** Sauvegardez le dossier `wp-content/plugins/postal-warmup`.
3.  **Options :** Vérifiez que la table `wp_options` est saine (option `pw_settings`).

## 2. Remplacement des Fichiers
Cette version est une refonte majeure (Refactoring PSR-4).
1.  Désactivez le plugin actuel.
2.  Supprimez **intégralement** le dossier `postal-warmup` existant. Ne pas écraser les fichiers un par un (risque de fichiers fantômes).
3.  Téléversez le nouveau dossier `postal-warmup`.
4.  Activez le plugin.

## 3. Mise à jour de la Base de Données
Le plugin tentera automatiquement de mettre à jour le schéma via `dbDelta` à l'activation. Cependant, voici les changements SQL exacts appliqués :

```sql
-- Ajout des index de performance manquants
ALTER TABLE wp_postal_servers ADD INDEX idx_active (active);
ALTER TABLE wp_postal_servers ADD INDEX idx_priority (priority);
ALTER TABLE wp_postal_logs ADD INDEX idx_server_created (server_id, created_at);

-- Nouvelles tables pour le Warmup Adaptatif (si pas déjà présentes)
CREATE TABLE IF NOT EXISTS wp_postal_server_isp_stats (
    id bigint NOT NULL AUTO_INCREMENT,
    server_id int NOT NULL,
    isp_key varchar(50) NOT NULL,
    score int DEFAULT 100,
    warmup_day int DEFAULT 1,
    sent_today int DEFAULT 0,
    delivered_today int DEFAULT 0,
    fails_today int DEFAULT 0,
    last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_stat (server_id, isp_key),
    KEY idx_server (server_id),
    KEY idx_isp (isp_key)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table d'historique permanent (Logs légers)
CREATE TABLE IF NOT EXISTS wp_postal_stats_history (
    id bigint NOT NULL AUTO_INCREMENT,
    server_id int NOT NULL,
    template_id bigint DEFAULT NULL,
    message_id varchar(255) DEFAULT NULL,
    email_from varchar(255) DEFAULT NULL,
    event_type varchar(50) NOT NULL,
    timestamp datetime NOT NULL,
    meta longtext DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id),
    KEY idx_server_id (server_id),
    KEY idx_template_id (template_id),
    KEY idx_message_id (message_id),
    KEY idx_event_type (event_type),
    KEY idx_timestamp (timestamp)
) CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 4. Migration des Options
Le plugin migre automatiquement les anciennes options individuelles (ex: `pw_daily_limit`) vers un tableau unique `pw_settings`.
- **Anciennes clés :** `pw_global_tag`, `pw_enable_logging`, `pw_queue_batch_size`, etc.
- **Nouvelle clé :** `pw_settings` (tableau sérialisé).

Vérifiez après activation dans **Postal Warmup > Paramètres** que vos réglages sont conservés.

## 5. Changements Importants
- **PHP 8.1+ Requis :** Le code utilise désormais le typage strict (`declare(strict_types=1)`).
- **Webhooks :** L'URL du webhook a changé ou nécessite désormais un token de sécurité si le "Strict Mode" est activé.
    - Nouvelle URL : `https://votre-site.com/wp-json/postal-warmup/v1/webhook?token=VOTRE_SECRET`
    - Vérifiez l'onglet **Sécurité** pour récupérer la nouvelle URL à mettre dans Postal.
- **Cron :** Les tâches planifiées sont gérées plus proprement. Il peut falloir jusqu'à 1 minute pour que le processeur de file d'attente redémarre.

## 6. Vérification Post-Migration
1.  **Dashboard :** Vérifiez que les graphiques s'affichent et que les IPs des serveurs sont résolues (plus de "127.0.0.1").
2.  **Templates :** Vérifiez que vos templates sont listés. Testez l'éditeur.
3.  **Envoi Test :** Allez dans **Serveurs**, cliquez sur "Tester la connexion".
4.  **Shortcodes :** Si vous utilisez `[email_warmup]`, vérifiez qu'il génère bien un lien mailto.

## 7. Rollback
En cas d'échec critique (Erreur 500, page blanche) :
1.  Supprimez le dossier `postal-warmup`.
2.  Restaurez la sauvegarde des fichiers de la version précédente.
3.  Les tables de base de données sont rétro-compatibles (les nouvelles colonnes/tables seront juste ignorées par l'ancienne version).
