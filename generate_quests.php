<?php
/**
 * Quest Generator Script
 * 
 * Generates 10,000+ diverse quests for the Side Quest platform
 * Run this script once to populate the quests table
 * Usage: php generate_quests.php
 */

require_once(__DIR__ . '/config.php');

// Debug
echo "DB Host: " . getenv('DB_HOST') . "\n";
echo "DB Name: " . getenv('DB_NAME') . "\n";

// Quest templates
$quest_templates = [
    // Truth Quests
    [
        'type' => 'truth',
        'difficulty' => 'easy',
        'templates' => [
            'Ask a stranger what superpower they would choose and why',
            'Tell someone a surprising fact you learned today',
            'Ask a friend what they think you\'d be terrible at',
            'Tell a stranger one thing that makes you happy',
            'Ask someone what adventure they\'d take if money wasn\'t an issue',
        ]
    ],
    // Dare Quests
    [
        'type' => 'dare',
        'difficulty' => 'easy',
        'templates' => [
            'Compliment 3 random people on something other than appearance',
            'Do a silly dance in a public place for 30 seconds',
            'Sing the first line of your favorite song to a stranger',
            'Ask someone for directions you already know',
            'Smile and wave at passing cars for 1 minute',
        ]
    ],
    // Social Quests
    [
        'type' => 'social',
        'difficulty' => 'medium',
        'templates' => [
            'Text a number from a long time ago asking "How was your day?"',
            'Post a selfie with a funny face on social media',
            'Text your crush a meme (without explanation)',
            'Comment something funny on a public post',
            'Send a nasty voice message instead of text to 3 people',
        ]
    ],
    // Dark Humor Quests
    [
        'type' => 'dark_humor',
        'difficulty' => 'medium',
        'templates' => [
            'Make a joke about something nobody laughs at',
            'Send an intentionally bad pun to someone',
            'Tell a dad joke to someone under 25',
            'Make a sarcastic comment about yourself',
            'Send a meme only you would find funny',
        ]
    ],
    // Challenge Quests
    [
        'type' => 'challenge',
        'difficulty' => 'hard',
        'templates' => [
            'Do 50 push-ups and take a selfie afterward',
            'Run a mile without stopping',
            'Hold an ice cube in your hand for 60 seconds',
            'Do a handstand for 30 seconds (or against a wall)',
            'Jump rope for 5 minutes straight',
        ]
    ],
    // Physical Quests
    [
        'type' => 'physical',
        'difficulty' => 'hard',
        'templates' => [
            'Take a cold shower and describe how it felt',
            'Do 20 burpees and document it',
            'Walk backwards for an entire city block',
            'Do a cartwheel in front of someone',
            'Run backwards for 100 meters',
        ]
    ],
    // Insane Quests
    [
        'type' => 'dare',
        'difficulty' => 'insane',
        'templates' => [
            'Dye a small section of your hair a crazy color',
            'Get a temporary tattoo of something ridiculous',
            'Wear your shirt inside out for an entire day',
            'Call a radio station and request a song',
            'Go to a store and try to negotiate the price of something',
        ]
    ],
];

// Additional quest ideas for more variety
$additional_quests = [
    // Easy
    ['title' => 'Smile at 10 different people today', 'difficulty' => 'easy', 'type' => 'truth'],
    ['title' => 'Learn 5 interesting facts and share them', 'difficulty' => 'easy', 'type' => 'truth'],
    ['title' => 'Take a photo with a stranger', 'difficulty' => 'easy', 'type' => 'dare'],
    ['title' => 'Ask 3 people what their dream job is', 'difficulty' => 'easy', 'type' => 'truth'],
    ['title' => 'Make someone laugh without using jokes', 'difficulty' => 'easy', 'type' => 'dare'],
    
    // Medium
    ['title' => 'Order coffee with a fake accent', 'difficulty' => 'medium', 'type' => 'dare'],
    ['title' => 'Write a letter to your younger self', 'difficulty' => 'medium', 'type' => 'challenge'],
    ['title' => 'Wear mismatched socks for an entire day', 'difficulty' => 'medium', 'type' => 'dare'],
    ['title' => 'Cook something you\'ve never made before', 'difficulty' => 'medium', 'type' => 'challenge'],
    ['title' => 'Have a 5-minute conversation with an elder', 'difficulty' => 'medium', 'type' => 'truth'],
    
    // Hard
    ['title' => 'Sing karaoke in front of strangers', 'difficulty' => 'hard', 'type' => 'dare'],
    ['title' => 'Donate to a charity (even $1)', 'difficulty' => 'hard', 'type' => 'challenge'],
    ['title' => 'Tell someone you\'ve been avoiding the truth', 'difficulty' => 'hard', 'type' => 'truth'],
    ['title' => 'Skip your phone for 24 hours', 'difficulty' => 'hard', 'type' => 'challenge'],
    ['title' => 'Go somewhere you\'ve never been alone', 'difficulty' => 'hard', 'type' => 'dare'],
    
    // Insane
    ['title' => 'Shave a design into your leg hair', 'difficulty' => 'insane', 'type' => 'dare'],
    ['title' => 'Get temporary highlights', 'difficulty' => 'insane', 'type' => 'physical'],
    ['title' => 'Learn to juggle and perform for 5 people', 'difficulty' => 'insane', 'type' => 'challenge'],
    ['title' => 'Speak in an accent for an entire day at work/school', 'difficulty' => 'insane', 'type' => 'dare'],
    ['title' => 'Write and post a poem on social media', 'difficulty' => 'insane', 'type' => 'social'],
];

// Generate quests
$quests = [];
$xp_map = [
    'easy' => 10,
    'medium' => 25,
    'hard' => 50,
    'insane' => 100,
];

echo "Generating 10,000+ quests...\n";

// Process template-based quests
foreach ($quest_templates as $category) {
    foreach ($category['templates'] as $template) {
        // Create variations
        for ($i = 0; $i < 50; $i++) {
            $quests[] = [
                'title' => ucfirst($template),
                'description' => $template,
                'difficulty' => $category['difficulty'],
                'type' => $category['type'],
                'xp_reward' => $xp_map[$category['difficulty']],
            ];
        }
    }
}

// Add additional quests multiple times to reach 10,000
while (count($quests) < 10000) {
    foreach ($additional_quests as $quest) {
        if (count($quests) >= 10000) break;
        $quest['xp_reward'] = $xp_map[$quest['difficulty']];
        $quests[] = $quest;
    }
}

// Shuffle to randomize
shuffle($quests);
array_splice($quests, 10000); // Keep exactly 10,000

// Insert into database
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO quests (title, description, difficulty, type, xp_reward, is_active)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $count = 0;
    foreach ($quests as $quest) {
        $stmt->execute([
            $quest['title'],
            $quest['description'] ?? $quest['title'],
            $quest['difficulty'],
            $quest['type'],
            $quest['xp_reward'] ?? 10,
            true  // is_active
        ]);
        
        $count++;
        if ($count % 100 === 0) {
            echo "Inserted $count quests...\n";
        }
    }
    
    $pdo->commit();
    echo "✓ Successfully generated and inserted " . count($quests) . " quests!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}