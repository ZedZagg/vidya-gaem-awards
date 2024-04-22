<?php
namespace App\Controller;

use App\Service\ConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class StaticController extends AbstractController
{
    public function indexAction(RouterInterface $router, ConfigService $configService): Response
    {
        $defaultPage = $configService->getConfig()->getDefaultPage();
        $defaultRoute = $router->getRouteCollection()->get($defaultPage);

        return $this->forward($defaultRoute->getDefault('_controller'), $defaultRoute->getDefaults());
    }

    public function videosAction(): Response
    {
        return $this->render('videos.html.twig');
    }

    public function soundtrackAction(): Response
    {
        $preshow = [
//            ['vgas2021 mix', 'beat_shobon', 'Preshow'],
//            ['MAIN MIX FOR FINAL EXPORT_2', 'fv.exe', 'Preshow'],
//            ['vga 2021', 'nostalgia_junkie', 'Preshow'],
//            ['vga2021', 'W.T. Snacks', 'Preshow'],
        ];

        $tracks = [
            ['Time is the Enemy', 'Quantic', 'Most Hated Award'],
            ['Memory Reboot', 'VØJ', 'Least Worst Award'],
            ['Trigger Happy', 'TakMi', 'Least Worst Award'],
            ['In the Key of Blue', 'Quantic', 'Least Worst Award'],
            ["Don't Wanna Work", 'Drapht', 'Silcon Valley Bank Award'],
            ['Life in the Rain', 'Quantic', 'Silcon Valley Bank Award'],
            ['Pure Imagination (Cello Cover)', 'Ian Gottlieb', "Finnegan's Quake Award"],
            ['Purpose is Glorious', 'Natalie Holt', "Finnegan's Quake Award"],
            ['Open Pendant', 'Soulway Beats', 'Don Miguel Award'],
            ['Closed Pendant', 'Soulway Beats', 'Don Miguel Award'],
            ['Airborn', 'RPGMaker 2003 OST Remastered', 'Don Miguel Award'],
            ['cruiser', 'esprit 空想', 'A E S T H E T I C S Award'],
            ['Zelda: A Link To The Past - Dark World (Neon X Remix)', 'Neon X', 'A E S T H E T I C S Award'],
            ['Minish Woods', 'Baptiste Robert', 'Haptic Feedback Award'],
            ['hmmm look what u done did you found a secret :)', 'Pizza Tower', 'Haptic Feedback Award'],
            ['Can\'t Sleep Because It\'s Nighttime', 'Eastern and Little Nature Deity', 'Press X to Win the Award'],
            ['Into The Starfield', 'Inon Zur', 'Press X to Win the Award'],
            ['Edgar & Sabin\'s Theme', 'Final Fantasy VI', 'PXIXILS ARE XIRT Award'],
            ['Victory Fanfare', 'Final Fantasy VI', 'PXIXILS ARE XIRT Award'],
            ['Reunion', 'Dabu', 'PXIXILS ARE XIRT Award'],
            ['Alone in the World', 'Wild Arms OST', 'Pottery Award'],
            ['Unfulfilled Progress', 'Breath of Fire III', 'Pottery Award'],
            ['The 5th Exotic', 'Quantic', 'IP Twist Award'],
            ['Ground Dasher SFC - Demon Roots', 'Shade Music Laboratory', 'Kamige Award'],
            ['My Glorious Days', 'Rance IX', 'Kamige Award'],
            ['Route 6', 'Pokemon Clover', 'Diamond in the Rough Award'],
            ['A New Adventure Awaits', 'Bayonetta Origins', 'Diamond in the Rough Award'],
            ['Those Who Watch Stars (Saxaphone Cover)', 'subversiveasset', 'Hindenburg Award'],
            ['Catching Criminals on the Run', 'Tin Star', 'Hindenburg Award'],
            ['Battle 1', 'Final Fantasy Mystic Quest', 'Hindenburg Award'],
            ['Margarie Margarita', 'Super Mario RPG (SNES)', 'Hindenburg Award'],
            ['I\'m God', 'Clams Casino, Imogen Heap', 'Plot and Backstory Award'],
            ['Arnold', 'Luke Million', 'Ricardo Milos Award'],
            ['Dancin\' (KRONO Remix)', 'Aaron Smith', 'Ricardo Milos Award'],
            ['Malomart', 'Legend of Zelda: Twilight Princess', 'Hate Machine Award'],
            ['Summer Bang', 'PROUX ft. Melting Potheads, RHF Soundtrack', 'Rhythm Heaven Award'],
            ['Happiness', 'Bible Black Bonus CD', '/vr/ Award'],
            ['Star Prizes (A)', 'Tony Kinsey', '/vr/ Award'],
            ['Boss', 'Hirokazu Ando (Kirby\'s Adventure)', '/vr/ Award'],
            ['Intermission from Doom', 'Bobby Prince (Doom)', '/vr/ Award'],
            ['Title (Opening Version', 'Minako Hamano, Kozue Ishikawa (The Legend of Zelda: Link\'s Awakening)', '/vr/ Award'],
            ['Did You See The Ocean?', 'Hiroki Kikuta (Secret of Mana)', '/vr/ Award'],
            ['Pulling Ted onto the Roof', 'Peter McConnell, Michael Land, and Clint Bajakian (Day of the Tentacle)', '/vr/ Award'],
            ['Sinister', 'Bobby Prince (Doom)', '/vr/ Award'],
            ['Bring Me to Life (8-bit version)', 'Evanescence', 'Warioware Award'],
            ['Over There! WW1 Collection (Slowed and reverb)', '', 'Friday Night Frights Award'],
            ['Main Theme', 'Jerry Goldsmith, Alien', 'Friday Night Frights Award'],
            ['Main Titles', 'Hands Salter, Creature From the Black Lagoon', 'Friday Night Frights Award'],
            ['Children of the Damned', 'Ron Goodwin', 'Friday Night Frights Award'],
            ['Cherries Were Made For Eating', 'Brahman', 'Friday Night Frights Award'],
            ['Genesis/Mega Drive Music Pack', 'Steek Stook', 'Re-Bastard Award'],
            ['Happy Party Train', 'Love Live', 'Re-Bastard Award'],
            ['', 'Pokemon Clover', 'Redemption Arc Award'],
            ['Victory Fanfare', 'Final Fantasy VI', 'Redemption Arc Award'],
            ['Rules of Nature', 'Metal Gear Rising', 'Please Be Real Award'],
            ['Battle: Gentleman', 'Pokemon Clover', 'Please Be Real Award'],
            ['It Has to Be This Way', 'Metal Gear Rising', '/v/GA THROWBACK (2013)'],
            ['Opening Theme', 'Rebellion', '1CC Award'],
            ['Show Time under Leaden Skies', 'COOL&CREATE', '1CC Award'],
            ['Stab and Stomp! [BOSS - AIR]', 'Battle Garegga', '1CC Award'],
            ['Epilogue', 'Spirit Being', '1CC Award'],
            ['Morning Broadway', 'Keith Mansfield, The KPM Orchestra', 'Calvinball Award'],
            ['Toroko\'s Theme', 'Cave Story', 'Intro Sketch'],
            ['Rickroll Variations', 'JunWu', 'Credits'],
            ['Avertuneiro Antes Lance Mao (ending theme)', 'Suikoden', 'Credits'],
            ['Moon Trilogy - Promise (Finale Version)', 'Moon', 'Outro'],
            ['Snakes in the Grass', 'Quantic', 'AI Disclosure'],
            ['Common Knowledge', 'Quantic', 'AI Disclosure'],
            ['Guess Work', 'The Ocean Party', 'Intermission'],
            ['Otherside', 'Tom Staar and Eddie Thoneick', 'Intermission'],
            ['Hard Up', 'The Bamboos', 'Intermission'],
            ['Come Follow Me', 'The Shtooks', 'Intermission'],
            ['Baby Got Back', 'Sir Mix-A-Lot ft. the Seattle Symphony', 'Intermission'],
            ['End Theme', 'Sorya nai ze! ? Fureijā', 'Intermission'],
        ];

        return $this->render('soundtrack.html.twig', [
            'preshow' => $preshow,
            'tracks' => $tracks
        ]);
    }

    public function creditsAction(): Response
    {
        return $this->render('credits.html.twig');
    }

    public function trailersAction(): Response
    {
        return $this->render('trailers.html.twig');
    }
}
