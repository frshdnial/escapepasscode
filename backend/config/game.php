<?php
// Game content and rules. The answers ("code") never leave the server.
return [
    'timeLimitSeconds' => 60,   // per case
    'maxAttempts'      => 5,    // wrong combinations per case before the vault locks

    'cases' => [
        [
            'title' => 'The Warehouse Break-In',
            'rank'  => 'Rookie',
            'note'  => 'A patrol officer found this note taped inside the warehouse door. No context, just three questions.',
            'clues' => [
                ['tag' => 'Clue 1', 'text' => 'Count the letters in the word “CODE.”'],
                ['tag' => 'Clue 2', 'text' => 'Count the colors in a rainbow.'],
                ['tag' => 'Clue 3', 'text' => 'A clock shows 3:00. What number is the minute hand pointing to? Take the first digit of that number.'],
            ],
            'code' => '471',
        ],
        [
            'title' => 'The Forged Signature',
            'rank'  => 'Junior Detective',
            'note'  => 'The forger left a note in the margins of the contract, written like a puzzle for whoever found it first.',
            'clues' => [
                ['tag' => 'Clue 1', 'text' => 'Count the sides of a hexagon.'],
                ['tag' => 'Clue 2', 'text' => 'How many continents are there, by the standard seven-continent model?'],
                ['tag' => 'Clue 3', 'text' => 'The break-in happened on a Wednesday. Counting Monday as day 1, what number is Wednesday?'],
            ],
            'code' => '673',
        ],
        [
            'title' => 'The Poisoned Wine',
            'rank'  => 'Detective',
            'note'  => 'A dinner party, one poisoned glass, and a cipher scratched into the tablecloth by the victim.',
            'clues' => [
                ['tag' => 'Clue 1', 'text' => 'The Roman numeral “IX” stands for what number?'],
                ['tag' => 'Clue 2', 'text' => 'Take a dozen, then subtract half a dozen.'],
                ['tag' => 'Clue 3', 'text' => "The victim's watch stopped at 8:15. Add the hour to the tens digit of the minutes."],
            ],
            'code' => '969',
        ],
        [
            'title' => 'The Bank Vault Heist',
            'rank'  => 'Senior Investigator',
            'note'  => 'The thieves left the vault combination scattered across three separate ransom notes — clearly enjoying themselves.',
            'clues' => [
                ['tag' => 'Clue 1', 'text' => 'Count the letters in “DETECTIVE,” then subtract the number of vowels in that word.'],
                ['tag' => 'Clue 2', 'text' => "A witness recalls the getaway car's plate ending in an odd prime number greater than 5 and less than 10."],
                ['tag' => 'Clue 3', 'text' => 'The security log shows 11:50. Round to the nearest hour, then take the last digit of that hour.'],
            ],
            'code' => '572',
        ],
        [
            'title' => 'The Final Confrontation',
            'rank'  => 'Master Detective',
            'note'  => 'The suspect is cornered and taunts you with one last riddle before the confession — solve it to close the case for good.',
            'clues' => [
                ['tag' => 'Clue 1', 'text' => 'Today is Friday. Counting Sunday as day 1, the crime happened 3 days earlier. What number is that day?'],
                ['tag' => 'Clue 2', 'text' => "The suspect's height is 180cm. Find its digital root (keep summing the digits until one digit remains)."],
                ['tag' => 'Clue 3', 'text' => 'A chessboard has 32 squares of one color. Take the tens digit of that number.'],
            ],
            'code' => '393',
        ],
    ],
];
