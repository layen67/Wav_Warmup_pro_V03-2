# Roadmap Technique : Postal Warmup Pro (Post-v3.4.0)

Ce document décrit les évolutions techniques recommandées pour les futures versions, basées sur l'audit d'architecture.

## 1. Interface en Ligne de Commande (WP-CLI)
Implémentation de commandes pour faciliter l'administration système et le debug.

**Commandes proposées :**
- `wp postal-warmup status` : Affiche l'état de la queue, les serveurs actifs et les erreurs récentes.
- `wp postal-warmup queue process [--force]` : Lance le traitement manuel de la file d'attente.
- `wp postal-warmup server test <id>` : Envoie un email de test depuis un serveur spécifique.
- `wp postal-warmup db optimize` : Purge et optimise les tables de logs.

**Spécifications :**
- Classe : `PostalWarmup\CLI\Commands`
- Namespace : `postal-warmup`

## 2. API REST Publique (Read-Only)
Exposer des endpoints sécurisés pour le monitoring externe (ex: Grafana, Zabbix) ou un dashboard centralisé SaaS.

**Endpoints :**
- `GET /postal-warmup/v1/stats/global` : Volume jour, taux de succès.
- `GET /postal-warmup/v1/servers/health` : Liste des serveurs avec latence et état.

**Sécurité :**
- Authentification : Application Passwords (Core WP) ou Clé API dédiée en lecture seule.
- Rate Limiting : 60 req/min.

## 3. Synchronisation Bidirectionnelle des Logs (Mode Hybride)
Pour réduire la taille de la base de données locale (`wp_postal_logs` qui grossit très vite), ne stocker que les métadonnées essentielles et récupérer le contenu complet via l'API Postal à la demande.

**Architecture :**
- **Local (DB) :** `id`, `message_id`, `status`, `timestamp`.
- **Distant (Postal) :** `body`, `headers`, `delivery_details`.
- **Vue Admin :** Lors du clic sur "Détails", appel AJAX -> Proxy PHP -> API Postal GET /messages/{id}.

## 4. Audit DNS Automatisé
Bouton "Vérifier DNS" dans la liste des serveurs.
- Vérifie la présence et la validité de :
    - SPF (`v=spf1 include:spf.postal...`)
    - DKIM (Sélecteur postal)
    - DMARC
    - CNAME (Tracking)
- Utilise `dns_get_record()` en PHP ou l'API Postal `GET /domains`.

## 5. Failover de Pool IP
Logique de bascule automatique si un serveur est blacklisté ou en échec critique.
- **Trigger :** Taux d'erreur > 10% sur 1h OU Blacklist détectée (via API externe).
- **Action :** Passer le serveur en `active=0`. Activer un serveur de backup (Pool de secours).
- **Notification :** Alerte Slack/Email immédiate.

## 6. Support Multisite (WPMU)
- **Configuration Réseau :** Définir des serveurs au niveau "Network Admin" partagés entre tous les sites.
- **Quotas par Site :** Limiter le volume d'envoi pour chaque sous-site.
- **Tables Globales :** Utiliser `$wpdb->base_prefix` pour les logs et serveurs si mode centralisé.

## 7. Tests Unitaires & CI
Mise en place d'un pipeline d'intégration continue solide.
- **Framework :** PestPHP ou PHPUnit.
- **Couverture :**
    - `Encryption` (Critique)
    - `QueueManager` (Logique de retry et scheduling)
    - `TemplateLoader` (Parsing spintax)
- **CI :** GitHub Actions pour lancer `phpcs`, `phpstan` et `phpunit` à chaque push.
