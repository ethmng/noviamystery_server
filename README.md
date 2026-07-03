# NoviaMystery — Serveur API

API REST PHP pour le jeu d'enquête **NoviaMystery**. Elle génère chaque jour un scénario unique (victime, suspects, indices, labyrinthe) et valide les accusations des joueurs. **La solution n'est jamais envoyée au client.**

## Prérequis

- PHP **8.1+** (recommandé : 8.3)
- Extension `json` (incluse par défaut)
- Apache avec `mod_rewrite` **ou** serveur PHP intégré
- Dossier `data/` **inscriptible** par le processus web

## Installation rapide

```bash
cd server
mkdir -p data
echo '[]' > data/leaderboard.json
chmod 755 data
chmod 664 data/leaderboard.json
```

### Développement local (PHP intégré)

```bash
php -S localhost:8080 index.php
```

Le client Python peut pointer vers :

```python
API_BASE_URL = "http://localhost:8080"
```

### Docker

```bash
docker compose up -d
```

API disponible sur [http://localhost:8080](http://localhost:8080).

### Apache (production)

1. Déployer le dossier `server/` dans le vhost (ex. `python.api.noviacode.fr`)
2. Activer `mod_rewrite` — le fichier `.htaccess` redirige vers `index.php`
3. Vérifier que `data/` est inscriptible :

```bash
chown -R www-data:www-data data/
chmod 755 data && chmod 664 data/leaderboard.json
```

4. Le client en production utilise :

```
https://python.api.noviacode.fr?route=/mystery/...
```

## Configuration

Fichier `config.php` :

| Constante | Description |
|-----------|-------------|
| `API_VERSION` | Version exposée dans `/mystery/status` |
| `MAINTENANCE_MODE` | `true` = mode en ligne désactivé |
| `MAINTENANCE_MESSAGE` | Message affiché aux clients |
| `PLAYER_NAME_MAX_LENGTH` | Longueur max du pseudo (32) |
| `LEADERBOARD_MAX_ENTRIES` | Entrées max dans le classement (20) |

### Activer la maintenance

```php
const MAINTENANCE_MODE = true;
```

Le client affichera un avertissement et grisera le bouton « Mode en-ligne ».

## Endpoints

Base : `?route=/mystery/<endpoint>` ou `/mystery/<endpoint>` (Apache)

### `GET /mystery/status`

Vérifie que l'API est opérationnelle.

```json
{
  "status": "ok",
  "version": "1.1.0",
  "date": "2026-07-03"
}
```

Maintenance (`MAINTENANCE_MODE = true`) :

```json
{
  "status": "maintenance",
  "message": "Le serveur est en cours de maintenance...",
  "version": "1.1.0"
}
```

### `GET /mystery/scenario`

Retourne le scénario **public** du jour (sans la solution).

```json
{
  "scenario_id": "scenario-2026-07-03-abc123",
  "date": "2026-07-03",
  "victim": "Comtesse Aubergine",
  "rooms": [{"name": "Salon", "col": 0, "row": 0}],
  "suspects": [{"name": "...", "room": "...", "alibi": "..."}],
  "clues": [{"name": "...", "description": "...", "category": "weapon", "value": "...", "room": "..."}],
  "weapons_pool": ["Chandelier", "Couteau", "Dague", "Revolver"]
}
```

Champs **absents** (côté serveur uniquement) : `culprit`, `weapon`, `room`.

Le scénario est **déterministe** : même date → même intrigue pour tous les joueurs.

### `POST /mystery/solve`

Valide l'accusation du joueur. Réponse toujours en **HTTP 200**.

**Corps JSON :**

```json
{
  "player": "Ethan",
  "suspect": "Comte Brun",
  "weapon": "Dague",
  "room": "Bureau",
  "scenario_id": "scenario-2026-07-03-abc123",
  "scenario_date": "2026-07-03",
  "clues_found": 3,
  "time_seconds": 312
}
```

Champs requis : `player`, `suspect`, `weapon`, `room`.

**Réponse (correct) :**

```json
{
  "correct": true,
  "explanation": "Bravo ! Vous avez trouvé le bon suspect, la bonne arme et la bonne pièce."
}
```

**Réponse (incorrect) :**

```json
{
  "correct": false,
  "explanation": "Ce n'est pas la bonne combinaison. Continuez à chercher."
}
```

La solution n'est **jamais** révélée en cas d'échec. Une bonne réponse est enregistrée dans `data/leaderboard.json`.

### `GET /mystery/leaderboard`

```json
[
  {
    "rank": 1,
    "player": "Alice",
    "clues_found": 5,
    "time_seconds": 280,
    "date": "2026-07-03",
    "scenario_id": "scenario-2026-07-03-abc123"
  }
]
```

Tri : temps le plus court, puis plus d'indices trouvés.

## Structure du projet

```
server/
├── index.php           # Routeur API + CORS
├── config.php          # Configuration
├── generator.php       # Génération scénario + classement
├── .htaccess           # Réécriture Apache
├── data/
│   ├── .htaccess       # Bloque l'accès HTTP direct au JSON
│   ├── .gitkeep
│   └── leaderboard.json
├── tests/
│   └── api_smoke.sh    # Tests manuels / CI
├── docker-compose.yml
└── .github/workflows/ci.yml
```

## Tests

```bash
# Terminal 1
php -S localhost:8080 index.php

# Terminal 2
chmod +x tests/api_smoke.sh
./tests/api_smoke.sh http://localhost:8080
```

## Sécurité

- La solution (`culprit`, `weapon`, `room`) reste côté serveur
- `data/leaderboard.json` protégé par `.htaccess` (Apache)
- Écriture du classement avec verrou fichier (`flock`)
- CORS ouvert (`*`) pour le client desktop — restreindre en production si besoin
- Validation du pseudo (1–32 caractères, trim)

## Intégration client Python

Le client (`../client`) consomme cette API via `network/api.py` :

| Étape | Endpoint |
|-------|----------|
| Vérification en ligne | `GET /mystery/status` |
| Chargement intrigue | `GET /mystery/scenario` |
| Accusation | `POST /mystery/solve` |
| Classement | `GET /mystery/leaderboard` |

En cas d'indisponibilité, le client bascule en **mode hors-ligne** (`core/offline_scenario.py`).

## Déploiement (checklist)

Uploadez **tous** ces fichiers ensemble :

```
index.php
config.php
generator.php
.htaccess
data/.htaccess
data/leaderboard.json   (contenu : [])
```

Puis vérifiez :

```bash
curl "https://python.api.noviacode.fr/?route=/mystery/status"
curl "https://python.api.noviacode.fr/deploy_check.php"
```

Réponse attendue du status :

```json
{"status": "ok", "version": "1.1.0", "date": "..."}
```

Supprimez `deploy_check.php` après validation.

### Cause fréquente du HTTP 500

- Fichiers déployés partiellement (`index.php` sans `config.php` ou `generator.php`)
- PHP &lt; 8.0 avec ancienne syntaxe `mixed` (corrigé en 1.1.0)
- Dossier `data/` non inscriptible

## Dépannage

| Problème | Solution |
|----------|----------|
| **HTTP 500** | Déployer les 3 PHP + `data/`, lancer `deploy_check.php`, consulter logs Apache/PHP |
| JSON invalide | Vérifier `display_errors` désactivé, logs PHP |
| Classement vide après victoire | Droits d'écriture sur `data/` (`chmod 775 data`) |
| Client « serveur injoignable » | URL, firewall, CORS, HTTPS |
| Scénario différent chaque requête | Vérifier la date système du serveur |
| Maintenance non détectée | `MAINTENANCE_MODE` dans `config.php`, HTTP 200 attendu |

## Auteur

**Ethan MENAGE** — [ethanmng.pro@gmail.com](mailto:ethanmng.pro@gmail.com)

Projet éducatif NoviaCode — voir aussi le README du client dans `../client/README.md`.