<?php
declare(strict_types=1);

/**
 * Génération déterministe des scénarios et persistance du classement.
 * La solution (coupable, arme, pièce) reste côté serveur uniquement.
 */

function generateScenario(?string $date = null): array
{
    $date = $date ?? date('Y-m-d');
    $seedBase = 'noviacode-mystery-' . $date;

    $roomNames = [
        'Salon', 'Cuisine', 'Grenier', 'Bibliothèque', 'Hall',
        'Bureau', 'Salle de billard', 'Véranda', 'Cellier',
    ];

    $victims = [
        'Sir Edmund', 'Madame Blanc', 'Professeur Violet', 'Dame Pervenche',
        'Baron Cramoisy', 'Comtesse Aubergine', 'Monsieur Olive', 'Viscount Rosewood',
    ];

    $weaponCatalog = [
        'Chandelier', 'Couteau', 'Revolver', 'Corde', 'Poignard',
        'Bouteille', 'Marteau', 'Châsse', 'Dague',
    ];

    $suspectNames = [
        'Colonel Moutarde', 'Mademoiselle Rose', 'Reverend Olive',
        'Madame Pervenche', 'Professeur Violet', 'Docteur Lacrosse',
        'Comte Brun', 'Baron Noir', 'Miss Scarlett',
    ];

    $roomsShuffled = seededShuffle($roomNames, $seedBase . '-rooms');
    $weaponsShuffled = seededShuffle($weaponCatalog, $seedBase . '-weapons');
    $suspectNamesShuffled = seededShuffle($suspectNames, $seedBase . '-suspects');

    $victim = selectElement($victims, $seedBase . '-victim');
    $weapon = $weaponsShuffled[0];
    $crimeRoom = $roomsShuffled[0];
    $culprit = $suspectNamesShuffled[0];

    $suspects = [];
    foreach (array_slice($suspectNamesShuffled, 0, 6) as $index => $name) {
        $suspects[] = [
            'name' => $name,
            'room' => $roomsShuffled[1 + $index % (count($roomsShuffled) - 1)],
            'alibi' => buildAlibi($name, $seedBase . '-alibi-' . $index),
        ];
    }

    if (!in_array($culprit, array_column($suspects, 'name'), true)) {
        $suspects[5] = [
            'name' => $culprit,
            'room' => $crimeRoom,
            'alibi' => buildAlibi($culprit, $seedBase . '-alibi-culprit'),
        ];
    }

    $gridSize = (int) ceil(sqrt(count($roomNames)));
    $roomLayout = [];
    foreach ($roomNames as $index => $name) {
        $roomLayout[] = [
            'name' => $name,
            'col' => $index % $gridSize,
            'row' => (int) floor($index / $gridSize),
        ];
    }

    return [
        'scenario_id' => buildScenarioId($date, $culprit, $crimeRoom, $weapon),
        'date' => $date,
        'victim' => $victim,
        'culprit' => $culprit,
        'weapon' => $weapon,
        'room' => $crimeRoom,
        'rooms' => $roomLayout,
        'suspects' => $suspects,
        'clues' => buildClues($weapon, $crimeRoom, $culprit, $seedBase),
        'weapons_pool' => buildWeaponsPool($weapon, $seedBase),
    ];
}

/** Scénario public : sans la solution, envoyé au client. */
function buildPublicScenario(array $scenario): array
{
    $public = $scenario;
    unset($public['culprit'], $public['weapon'], $public['room']);
    return $public;
}

function buildWeaponsPool(string $crimeWeapon, string $seedBase): array
{
    $catalog = [
        'Chandelier', 'Couteau', 'Revolver', 'Corde', 'Poignard',
        'Bouteille', 'Marteau', 'Châsse', 'Dague',
    ];

    $shuffled = seededShuffle($catalog, $seedBase . '-weapons-pool');
    $pool = array_slice($shuffled, 0, 6);

    if (!in_array($crimeWeapon, $pool, true)) {
        $pool[0] = $crimeWeapon;
    }

    $pool = array_values(array_unique($pool));
    sort($pool, SORT_STRING);

    return $pool;
}

function seededShuffle(array $items, string $seed): array
{
    $items = array_values($items);
    $count = count($items);

    for ($i = $count - 1; $i > 0; $i--) {
        $hash = hash('sha256', $seed . '|' . $i);
        $random = hexdec(substr($hash, 0, 8));
        $j = $random % ($i + 1);
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
    }

    return $items;
}

function selectElement(array $items, string $seed)
{
    $hash = hash('sha256', $seed);
    $index = hexdec(substr($hash, 0, 8)) % count($items);
    return $items[$index];
}

function buildAlibi(string $name, string $seed): string
{
    $templates = [
        "J'étais en train de lire dans ma chambre, loin du tumulte.",
        "Je préparais un thé dans la cuisine quand le drame a éclaté.",
        "Je vérifiais les livres de la bibliothèque, comme chaque soir.",
        "Je me promenais dans le jardin avec le majordome.",
        "J'étais seul dans le bureau à finaliser des documents.",
        "Je me reposais sur le canapé en attendant le dîner.",
        "J'écrivais une lettre dans le salon, sous les yeux de témoins.",
        "Je rangeais des bouteilles dans le cellier à cette heure-là.",
        "Je jouais au billard ; plusieurs personnes peuvent le confirmer.",
        "J'observais la pluie depuis la véranda, perdu dans mes pensées.",
        "Je montais au grenier chercher une vieille malle.",
        "Je discutais avec d'autres invités près du hall d'entrée.",
    ];

    return selectElement($templates, $seed . '|' . $name);
}

function buildClues(string $weapon, string $room, string $culprit, string $seed): array
{
    $clueRooms = seededShuffle([
        'Salon', 'Cuisine', 'Grenier', 'Bibliothèque', 'Hall',
        'Bureau', 'Salle de billard', 'Véranda', 'Cellier',
    ], $seed . '-clue-rooms');

    return [
        [
            'name' => 'Empreinte de gant',
            'description' => 'Une trace de gant trempée dans de la cire.',
            'category' => 'weapon',
            'value' => $weapon,
            'room' => $clueRooms[1],
        ],
        [
            'name' => 'Porte entrouverte',
            'description' => 'La porte d\'une pièce était légèrement entrouverte.',
            'category' => 'room',
            'value' => $room,
            'room' => $clueRooms[2],
        ],
        [
            'name' => 'Mouchoir brodé',
            'description' => 'Un mouchoir marqué d\'initiales suspectes.',
            'category' => 'suspect',
            'value' => $culprit,
            'room' => $clueRooms[3],
        ],
        [
            'name' => 'Verre brisé',
            'description' => 'Un verre fêlé avec une trace de vin rouge.',
            'category' => 'misc',
            'value' => 'Vin rouge',
            'room' => $clueRooms[4],
        ],
        [
            'name' => 'Lettre déchirée',
            'description' => 'Une lettre incomplète évoquant une dispute récente.',
            'category' => 'misc',
            'value' => 'Lettre',
            'room' => $clueRooms[5],
        ],
    ];
}

function buildScenarioId(string $date, string $culprit, string $room, string $weapon): string
{
    $hash = substr(hash('sha256', $date . '|' . $culprit . '|' . $room . '|' . $weapon), 0, 10);
    return 'scenario-' . $date . '-' . $hash;
}

function getLeaderboardPath(): string
{
    return __DIR__ . '/data/leaderboard.json';
}

function loadLeaderboard(): array
{
    $path = getLeaderboardPath();
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    if ($content === false || trim($content) === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveLeaderboard(array $leaderboard): bool
{
    $path = getLeaderboardPath();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($leaderboard, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);

        return $written !== false;
    } finally {
        fclose($fp);
    }
}

function appendLeaderboardEntry(array $entry): bool
{
    $path = getLeaderboardPath();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $fp = fopen($path, 'c+');
    if ($fp === false) {
        return false;
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            return false;
        }

        $content = stream_get_contents($fp);
        $leaderboard = [];
        if ($content !== false && trim($content) !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $leaderboard = $decoded;
            }
        }

        $leaderboard[] = $entry;

        $json = json_encode($leaderboard, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);

        return $written !== false;
    } finally {
        fclose($fp);
    }
}

function getLeaderboard(): array
{
    $leaderboard = loadLeaderboard();

    usort($leaderboard, static function ($a, $b) {
        $timeA = (int) ($a['time_seconds'] ?? 0);
        $timeB = (int) ($b['time_seconds'] ?? 0);
        if ($timeA !== $timeB) {
            return $timeA <=> $timeB;
        }

        $cluesA = (int) ($a['clues_found'] ?? 0);
        $cluesB = (int) ($b['clues_found'] ?? 0);
        if ($cluesA !== $cluesB) {
            return $cluesB <=> $cluesA;
        }

        return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
    });

    $ranked = [];
    foreach ($leaderboard as $index => $row) {
        $row['rank'] = $index + 1;
        $ranked[] = $row;
    }

    return array_slice($ranked, 0, LEADERBOARD_MAX_ENTRIES);
}