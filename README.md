# FastDrop - Plateforme de transfert de fichiers volumineux

FastDrop est une plateforme interne sécurisée pour le dépôt et le transfert de fichiers volumineux (jusqu'à plusieurs dizaines de Go par fichier). Elle permet aux utilisateurs internes de déposer des fichiers et aux prestataires/clients externes de les récupérer via des identifiants temporaires sécurisés.

## 🚀 Fonctionnalités principales

### ✅ Upload et stockage
- **Upload chunké** : Support des fichiers très volumineux avec reprise automatique
- **Stockage flexible** : Support local et S3/MinIO
- **Vérification d'intégrité** : Calcul automatique de checksums SHA-256
- **Gestion des quotas** : Contrôle du stockage par utilisateur
- **Scan antivirus** : Intégration ClamAV pour la sécurité

### 🔐 Sécurité et authentification
- **Authentification interne** : Système de comptes avec rôles (Admin, Uploader, Viewer)
- **Tokens temporaires** : Liens de téléchargement avec expiration et limitations
- **Chiffrement** : HTTPS obligatoire + stockage sécurisé
- **Audit complet** : Traçabilité de toutes les actions
- **Rate limiting** : Protection contre les abus

### 📊 Administration
- **Dashboard complet** : Vue d'ensemble de l'activité
- **Gestion des utilisateurs** : Création, modification, quotas
- **Monitoring** : Statistiques de stockage, téléchargements, activité
- **Maintenance** : Purge automatique, nettoyage des logs

## 🛠️ Architecture technique

### Stack technologique
- **Backend** : Symfony 6.4 (PHP 8.2+)
- **Base de données** : PostgreSQL 16
- **Cache/Sessions** : Redis
- **Stockage** : Local ou S3/MinIO compatible
- **Frontend** : Twig + Bootstrap 5 + JavaScript vanilla
- **Serveur web** : Nginx + PHP-FPM
- **SSL** : Let's Encrypt (recommandé)

### Sécurité
- **Tokens HMAC** : Signature cryptographique des liens
- **Rate limiting** : Protection contre les attaques par force brute
- **Audit trail** : Logs détaillés de toutes les opérations
- **Isolation** : Pas d'exposition d'API publique
- **Chiffrement** : Transit HTTPS + stockage sécurisé

## 📦 Installation

### Prérequis
- PHP 8.2+
- PostgreSQL 16+
- Redis
- Nginx
- Composer
- Symfony CLI

### Installation rapide

1. **Cloner le projet**
```bash
git clone <repository-url> fastdrop
cd fastdrop
```

2. **Installer les dépendances**
```bash
composer install
```

3. **Configurer la base de données**
```bash
# Créer la base de données
createdb fma-bdd

# Configurer .env.local
echo 'DATABASE_URL="postgresql://fma-user:password@127.0.0.1:5432/fma-bdd?serverVersion=16&charset=utf8"' > .env.local
echo 'APP_SECRET="your-secret-key-here"' >> .env.local
echo 'HMAC_SECRET="your-hmac-secret-key"' >> .env.local
```

4. **Exécuter les migrations**
```bash
php bin/console doctrine:migrations:migrate
```

5. **Créer un utilisateur admin**
```bash
php bin/console app:create-admin-user
```

6. **Démarrer le serveur**
```bash
symfony server:start
```

### Déploiement en production

Utilisez le script de déploiement automatisé :

```bash
sudo ./deploy.sh
```

Le script configure automatiquement :
- Nginx avec SSL
- PHP-FPM
- PostgreSQL
- Redis
- Firewall
- Cron jobs
- Services systemd

## ⚙️ Configuration

### Variables d'environnement

Créez un fichier `.env.local` avec les paramètres suivants :

```env
# Base de données
DATABASE_URL="postgresql://fma-user:password@127.0.0.1:5432/fma-bdd?serverVersion=16&charset=utf8"

# Sécurité
APP_SECRET="your-secret-key-change-in-production"
HMAC_SECRET="your-hmac-secret-key-change-in-production"

# Redis
REDIS_URL="redis://localhost:6379"

# Stockage
STORAGE_TYPE="local"  # ou "s3"
STORAGE_LOCAL_PATH="%kernel.project_dir%/var/storage"
STORAGE_S3_ENDPOINT="https://minio.example.com"
STORAGE_S3_KEY="your-access-key"
STORAGE_S3_SECRET="your-secret-key"
STORAGE_S3_BUCKET="fastdrop-files"
STORAGE_S3_REGION="us-east-1"

# Antivirus
CLAMAV_HOST="localhost"
CLAMAV_PORT="3310"
```

### Configuration des clés HMAC

La clé HMAC est cruciale pour la sécurité des tokens. Générez une clé forte :

```bash
# Générer une clé aléatoire
openssl rand -base64 32
```

Placez cette clé dans `HMAC_SECRET` et gardez-la secrète.

### Configuration des quotas

Les quotas sont définis par utilisateur en octets :

- **Quota illimité** : `null`
- **1 GB** : `1073741824`
- **10 GB** : `10737418240`
- **100 GB** : `107374182400`

## 🔧 Commandes de maintenance

### Purge des fichiers expirés
```bash
# Purger les fichiers expirés depuis 7 jours
php bin/console app:purge-expired-files --days=7

# Mode dry-run (simulation)
php bin/console app:purge-expired-files --dry-run
```

### Nettoyage des logs d'audit
```bash
# Garder les logs des 365 derniers jours
php bin/console app:cleanup-audit-logs --days=365
```

### Création d'utilisateurs
```bash
# Créer un admin
php bin/console app:create-admin-user

# Créer un utilisateur standard via l'interface admin
```

## 📋 Utilisation

### Pour les utilisateurs internes

1. **Connexion** : Accédez à `/login` avec vos identifiants
2. **Upload** : 
   - Fichiers < 100MB : Upload direct
   - Fichiers > 100MB : Upload par chunks (recommandé)
3. **Génération de liens** : Créez des liens temporaires avec options
4. **Gestion** : Consultez vos fichiers et l'historique

### Pour les prestataires externes

1. **Accès** : Utilisez le lien fourni par l'utilisateur interne
2. **Authentification** : Saisissez le mot de passe si requis
3. **Téléchargement** : Le fichier se télécharge automatiquement
4. **Limitations** : Respect des quotas et dates d'expiration

### Pour les administrateurs

1. **Dashboard** : Vue d'ensemble de l'activité
2. **Gestion utilisateurs** : Création, modification, quotas
3. **Monitoring** : Statistiques détaillées
4. **Maintenance** : Purge, logs, configuration

## 🔒 Sécurité

### Bonnes pratiques

1. **Chiffrement** : Utilisez toujours HTTPS en production
2. **Clés** : Changez les clés par défaut (APP_SECRET, HMAC_SECRET)
3. **Base de données** : Utilisez des mots de passe forts
4. **Firewall** : Limitez l'accès aux ports nécessaires
5. **Sauvegardes** : Sauvegardez régulièrement la base de données
6. **Mises à jour** : Maintenez le système à jour

### Rotation des clés

Pour changer la clé HMAC (nécessite une maintenance) :

1. Planifiez une maintenance
2. Changez `HMAC_SECRET`
3. Redémarrez l'application
4. Les anciens tokens deviendront invalides

## 📊 Monitoring

### Métriques importantes

- **Stockage utilisé** : Espace disque occupé
- **Téléchargements** : Volume et fréquence
- **Utilisateurs actifs** : Connexions récentes
- **Fichiers expirés** : À purger
- **Erreurs** : Logs d'erreur à surveiller

### Alertes recommandées

- Quota utilisateur > 90%
- Erreurs de stockage
- Échecs d'authentification multiples
- Tentatives d'accès non autorisées

## 🚨 Dépannage

### Problèmes courants

**Upload échoue**
- Vérifiez les permissions du répertoire de stockage
- Contrôlez le quota utilisateur
- Vérifiez la configuration PHP (upload_max_filesize, post_max_size)

**Tokens invalides**
- Vérifiez la configuration HMAC_SECRET
- Contrôlez les dates d'expiration
- Vérifiez les limitations de téléchargement

**Performance lente**
- Optimisez la base de données (VACUUM, ANALYZE)
- Vérifiez l'espace disque disponible
- Contrôlez la configuration Redis

### Logs

Les logs sont disponibles dans :
- **Application** : `var/log/dev.log`
- **Nginx** : `/var/log/nginx/error.log`
- **PHP-FPM** : `/var/log/php8.2-fpm.log`
- **PostgreSQL** : `/var/log/postgresql/`

## 🤝 Contribution

### Développement

1. Fork le projet
2. Créez une branche feature
3. Committez vos changements
4. Poussez vers la branche
5. Ouvrez une Pull Request

### Tests

```bash
# Tests unitaires
php bin/phpunit

# Tests d'intégration
php bin/phpunit --testsuite=integration
```

## 📄 Licence

Ce projet est sous licence propriétaire. Tous droits réservés.

## 🆘 Support

Pour toute question ou problème :

1. Consultez cette documentation
2. Vérifiez les logs d'erreur
3. Contactez l'équipe technique
4. Ouvrez une issue si nécessaire

---

**FastDrop** - Plateforme sécurisée de transfert de fichiers volumineux
