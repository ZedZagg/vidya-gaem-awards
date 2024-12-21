<?php

namespace App\Service;

use App\Entity\GameRelease;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IgdbService
{
    private const PLATFORM_MAP = [
        '3do interactive multiplayer' => ['3DO', null],
        'amazon fire tv' => ['Fire TV', 'mobile'],
        'amiga' => ['Amiga', null],
        'android' => ['Mobile', 'mobile'],
        'arcade' => ['Arcade', null],
        'atari jaguar' => ['Jaguar', null],
        'dos' => ['DOS', 'pc'],
        'dreamcast' => ['DC', null],
        'game boy' => ['GB', null],
        'game boy advance' => ['GBA', null],
        'game boy color' => ['GBC', null],
        'google stadia' => ['Stadia', null],
        'handheld electronic lcd' => ['Handheld', null],
        'ios' => ['Mobile', 'mobile'],
        'legacy mobile device' => ['Mobile', 'mobile'],
        'linux' => ['PC', 'pc'],
        'mac' => ['PC', 'pc'],
        'n-gage' => ['N-Gage', null],
        'new nintendo 3ds' => ['3DS', 'n3ds'],
        'neo geo aes' => ['Neo Geo', null],
        'neo geo cd' => ['Neo Geo', null],
        'neo geo mvs' => ['Neo Geo', null],
        'neo geo pocket' => ['Neo Geo', null],
        'neo geo pocket color' => ['Neo Geo', null],
        'nintendo 3ds' => ['3DS', 'n3ds'],
        'nintendo 64' => ['N64', null],
        'nintendo ds' => ['DS', null],
        'nintendo dsi' => ['DS', null],
        'nintendo entertainment system' => ['NES', null],
        'nintendo gamecube' => ['GCN', null],
        'nintendo switch' => ['Switch', 'switch'],
        'oculus rift' => ['Rift', ['pc', 'vr']],
        'oculus quest' => ['Quest', ['vr']],
        'oculus vr' => ['Oculus', ['vr']],
        'meta quest 2' => ['Quest 2', ['vr']],
        'meta quest 3' => ['Quest 3', ['vr']],
        'ouya' => ['Ouya', null],
        'pc (microsoft windows)' => ['PC', 'pc'],
        'playdate' => ['Playdate', null],
        'playstation' => ['PSX', null],
        'playstation 2' => ['PS2', null],
        'playstation 3' => ['PS3', 'ps3'],
        'playstation 4' => ['PS4', 'ps4'],
        'playstation 5' => ['PS5', 'ps5'],
        'playstation portable' => ['PSP', null],
        'playstation vita' => ['Vita', 'vita'],
        'playstation vr' => ['PSVR', ['ps4', 'vr']],
        'playstation vr2' => ['PSVR2', ['ps5', 'vr']],
        'sega saturn' => ['Saturn', null],
        'sega mega drive/genesis' => ['SMD', null],
        'super nintendo entertainment system' => ['SNES', null],
        'steamvr' => ['SteamVR', ['pc', 'vr']],
        'web browser' => ['PC', 'pc'],
        'wii' => ['Wii', 'wii'],
        'wiiu' => ['Wii U', 'wiiu'],
        'windows phone' => ['Mobile', 'mobile'],
        'windows mixed reality' => ['WMR', ['pc', 'vr']],
        'windows mobile' => ['Mobile', 'mobile'],
        'xbox' => ['Xbox', null],
        'xbox 360' => ['360', 'x360'],
        'xbox one' => ['XB1', 'xb1'],
        'xbox series x|s' => ['XSX', 'xsx'],
        'zx spectrum' => ['ZX', null],
    ];

    private ?string $accessToken = null;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $log,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function authed(): bool
    {
        return $this->accessToken !== null;
    }

    public function auth(): void
    {
        $response = $this->http->request('POST', 'https://id.twitch.tv/oauth2/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ],
        ])->getContent();

        $json = json_decode($response);
        $this->accessToken = $json->access_token;
    }

    public function getGames(int $year, int $offset = 0): array
    {
        if (!$this->authed()) {
            $this->auth();
        }

        $fields = [
            'name',
            'version_parent',
            'version_title',
            'url',
            'parent_game.name',
            'platforms.abbreviation',
            'platforms.name',
            'external_games.category',
            'external_games.url',
            'category',
            'status',
            'release_dates.human',
            'release_dates.date',
            'release_dates.y',
            'release_dates.platform.abbreviation',
            'release_dates.region',
            'release_dates.status.name',
            'first_release_date',
        ];

        $start = strtotime("$year-01-01T00:00:00Z");
        $end = strtotime("$year-12-31T23:59:59Z");

        $filters = [
            'first_release_date >=' . $start,
            'first_release_date <=' . $end,
            'category = (0, 2, 4, 8, 9)',
            '(release_dates.status = 6 | release_dates.status = null)',
            'version_title = null',
        ];

        $sorts = [
            'first_release_date asc'
        ];

        $limit = 500;

        $response = $this->http->request('POST', 'https://api.igdb.com/v4/games', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Client-ID' => $this->clientId,
            ],
            'body' => $this->queryToBody($fields, $filters, $sorts, $limit, $offset),
        ])->getContent();

        return json_decode($response, true);
    }

    private function queryToBody(array $fields = [], array $filters = [], array $sorts = [], ?int $limit = null, ?int $offset = 0): string
    {
        $lines = [];

        foreach ($fields as $field) {
            $lines[] = "fields $field;";
        }

        $lines[] = "where " . implode(" & ", $filters) . ";";

        foreach ($sorts as $sort) {
            $lines[] = "sort $sort;";
        }

        if ($limit !== null) {
            $lines[] = "limit $limit;";
        }

        $lines[] = "offset $offset;";

        return implode("\n", $lines);
    }

    public function addGamesToGameReleaseTable(array $games, bool $deleteExisting = true): void
    {
        if ($deleteExisting) {
            $gamesToRemove = $this->em->getRepository(GameRelease::class)
                ->createQueryBuilder('gr')
                ->where('gr.source = :source')
                ->andWhere('gr.deletedAt IS NULL')
                ->setParameter('source', 'igdb')
                ->getQuery()
                ->getResult();

            foreach ($gamesToRemove as $gameRelease) {
                $this->em->remove($gameRelease);
            }
            $this->em->flush();
        }

        $doNotImport = $this->em->getRepository(GameRelease::class)
            ->createQueryBuilder('gr')
            ->select('gr.name')
            ->where('gr.source = :source')
            ->andWhere('gr.deletedAt IS NOT NULL')
            ->setParameter('source', 'igdb')
            ->getQuery()
            ->getResult();

        $doNotImport = array_column($doNotImport, 'name');

        foreach ($games as $game) {
            if (strlen($game['name']) > 255) {
                $this->log->warning('Game name too long: ' . $game['name']);
                $game['name'] = substr($game['name'], 0, 255);
            }

            if (in_array($game['name'], $doNotImport)) {
                continue;
            }

            $gameRelease = new GameRelease($game['name']);
            $gameRelease->setUrl($game['url']);
            $gameRelease->setSource('igdb');

            $platforms = array_column($game['platforms'], 'name');
            $platforms = $this->normalisePlatforms($platforms, $this->getGameReleasePlatformMap());

            foreach ($platforms as $platform) {
                if (method_exists($gameRelease, 'set' . $platform)) {
                    $gameRelease->{'set'.$platform}(true);
                } else {
                    $this->log->warning("Unknown platform for game {$game['name']}: $platform");
                }
            }
            $this->em->persist($gameRelease);
        }

        $this->em->flush();
    }

    private function getUserReadablePlatformMap(): array
    {
        $map = [];
        foreach (self::PLATFORM_MAP as $platform => $values) {
            $map[$platform] = $values[0];
        }
        return $map;
    }

    private function getGameReleasePlatformMap(): array
    {
        $map = [];
        foreach (self::PLATFORM_MAP as $platform => $values) {
            $map[$platform] = $values[1];
        }
        return $map;
    }

    public function normalisePlatforms(array $gamePlatforms, array $platformMap): array
    {
        $normalisedPlatforms = [];

        foreach ($gamePlatforms as $platform) {
            $platform = mb_strtolower($platform);
            if (!array_key_exists($platform, $platformMap)) {
                $this->log->warning('<fg=yellow>Unknown platform (' . $platform . ')</>');
            } elseif ($platformMap[$platform] !== null) {
                if (is_array($platformMap[$platform])) {
                    $normalisedPlatforms = array_merge($normalisedPlatforms, $platformMap[$platform]);
                } else {
                    $normalisedPlatforms[] = $platformMap[$platform];
                }
            }
        }

        $normalisedPlatforms = array_unique($normalisedPlatforms);

        sort($normalisedPlatforms);
        return $normalisedPlatforms;
    }

    /**
     * Gets an array of strings that's suitable for using in a call to $autocompleter->setStrings.
     * @param array $games
     * @return string[]
     */
    public function getStringListForAutocompleter(array $games): array
    {
        $strings = [];

        foreach ($games as $game) {
            $platforms = array_column($game['platforms'], 'name');
            $platforms = $this->normalisePlatforms($platforms, $this->getUserReadablePlatformMap());

            $strings[] = $game['name'] . ' (' . implode(', ', $platforms) . ')';
        }

        sort($strings);

        return $strings;
    }
}
