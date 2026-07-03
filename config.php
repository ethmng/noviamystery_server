<?php
declare(strict_types=1);

/** Version de l'API exposée dans /mystery/status */
const API_VERSION = '1.1.0';

/** Mettre à true pour désactiver le mode en ligne côté client */
const MAINTENANCE_MODE = false;

const MAINTENANCE_MESSAGE = 'Le serveur est en cours de maintenance. Réessayez plus tard.';

/** Longueur max du pseudo joueur */
const PLAYER_NAME_MAX_LENGTH = 32;

/** Nombre max d'entrées conservées dans le classement */
const LEADERBOARD_MAX_ENTRIES = 20;